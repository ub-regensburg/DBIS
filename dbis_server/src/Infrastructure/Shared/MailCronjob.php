<?php

require_once __DIR__ . '/MailClient.php';
require_once __DIR__ . '/../../../config/DotEnv.php';

loadDotEnv("/var/www/.env");

$IS_PRODUCTIVE = filter_var(getenv('PRODUCTIVE'), FILTER_VALIDATE_BOOLEAN);

if ($IS_PRODUCTIVE) {
    $host = getenv('PRODUCTIVE_DBIS_DB_HOST');
} else {
    $host = getenv('DBIS_DB_HOST');
}

$user = getenv('DBIS_DB_USER');
$driver = 'DBIS_pdo_pgsql';
$pass = getenv('DBIS_DB_PASSWORD');
$db = getenv('DBIS_DB_DBNAME');
$port = getenv("DBIS_DB_PORT");
$psqlUrl = 'pgsql://' . getenv('DBIS_DB_USER') .
    ':' . getenv('DBIS_DB_PASSWORD') .
    '@' . getenv('DBIS_DB_HOST') .
    ':' . getenv('DBIS_DB_PORT') .
    '/' . getenv('DBIS_DB_NAME');


try {
    $mailClient = new App\Infrastructure\Shared\MailClient();

    $dsn = "pgsql:host=$host;port=$port;dbname=$db;";

    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $sql = "SELECT * FROM license WHERE created_at::date = CURRENT_DATE and type=1";
    $stmt = $pdo->query($sql);
    $licenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $englishSubjects = [];
    $germanSubjects = [];

    $englishTypes = [];
    $germanTypes = [];

    $englishSubjectsString = "";
    $germanSubjectsString = "";

    $englishTypesString = "";
    $germanTypesString = "";

    foreach ($licenses as $license) {
        $resourceId = $license['resource'];

        $resourceQuery = "SELECT title FROM resource WHERE id = :resourceId";
        $resourceStmt = $pdo->prepare($resourceQuery);
        $resourceStmt->execute([':resourceId' => $resourceId]);
        $resourceTitle = $resourceStmt->fetchColumn();

        $subjectQuery = "select DISTINCT ON (subject.id) * FROM subject join subject_for_resource on subject.id = subject_for_resource.subject WHERE subject_for_resource.resource = :resourceId;";
        $subjectStmt = $pdo->prepare($subjectQuery);
        $subjectStmt->execute([':resourceId' => $resourceId]);
        $subjects = $subjectStmt->fetchAll();

        foreach ($subjects as $subject) {
            $title = json_decode($subject['title'], true);
        
            if (isset($title['en'])) {
                $englishSubjects[] = $title['en'];
            }
        
            if (isset($title['de'])) {
                $germanSubjects[] = $title['de'];
            }
        }

        $typeQuery = "select DISTINCT ON (resource_type.id) * FROM resource_type join resource_type_for_resource on resource_type.id = resource_type_for_resource.resource_type WHERE resource_type_for_resource.resource = :resourceId;";
        $typeStmt = $pdo->prepare($typeQuery);
        $typeStmt->execute([':resourceId' => $resourceId]);
        $types = $typeStmt->fetchAll();

        foreach ($types as $type) {
            $title = json_decode($type['title'], true);
        
            if (isset($title['en'])) {
                $englishTypes[] = $title['en'];
            }
        
            if (isset($title['de'])) {
                $germanTypes[] = $title['de'];
            }
        }

        $englishSubjectsString = implode(', ', $englishSubjects);
        $germanSubjectsString = implode(', ', $germanSubjects);
        $subjectsString = array("de" => $germanSubjectsString, "en" => $englishSubjectsString);

        $englishTypesString = implode(', ', $englishTypes);
        $germanTypesString = implode(', ', $germanTypes);
        $typesString = array("de" => $germanTypesString, "en" => $englishTypesString);

        $mailClient->informNewFreeDatabase($resourceId, $resourceTitle, $subjectsString, $typesString);

        echo "Email sent for resource ID " . $resourceId . " and title $resourceTitle\n";
    }

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}