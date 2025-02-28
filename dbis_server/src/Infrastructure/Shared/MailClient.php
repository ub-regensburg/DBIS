<?php

namespace App\Infrastructure\Shared;


/**
 * MailClient
 *
 */

error_reporting(E_ALL & ~E_DEPRECATED);

class MailClient
{
    private string $databaseIsHidden;
    private string $databaseIsVisible;
    private string $freeToPaidDatabase;
    private string $paidToFreeDatabase;
    private string $newFreeDatabase; 

    private $isProductive;

    public function __construct()
    {
        require_once __DIR__ . '/../../../config/DotEnv.php';

        loadDotEnv("/var/www/.env");

        $this->databaseIsHidden = file_get_contents(__DIR__ . '/MailClient/database_is_hidden.txt');
        $this->databaseIsVisible = file_get_contents(__DIR__ . '/MailClient/database_is_visible.txt');
        $this->freeToPaidDatabase = file_get_contents(__DIR__ . '/MailClient/free_to_paid_database.txt');
        $this->paidToFreeDatabase = file_get_contents(__DIR__ . '/MailClient/paid_to_free_database.txt');
        $this->newFreeDatabase = file_get_contents(__DIR__ . '/MailClient/new_free_database.txt');

        $this->isProductive = filter_var(getenv('PRODUCTIVE'), FILTER_VALIDATE_BOOLEAN);
    }

    public function informDatabaseIsHidden($databaseId, $databaseTitle) {
        $message = str_replace(['{databaseId}', '{databaseTitle}'], [$databaseId, $databaseTitle], $this->databaseIsHidden);

        $subject = "DBIS: Datenbank bietet keinen Zugang mehr - $databaseTitle | DBIS: Database no longer offers access - $databaseTitle";

        $this->sendMail($subject, $message);
    }

    public function informDatabaseIsVisible($databaseId, $databaseTitle) {
        $message = str_replace(['{databaseId}', '{databaseTitle}'], [$databaseId, $databaseTitle], $this->databaseIsVisible);

        $subject = "DBIS: Datenbank bietet wieder Zugang - $databaseTitle | DBIS: Database offers access again - $databaseTitle";

        $this->sendMail($subject, $message);
    }

    public function informDatabaseChangedFromFreeToPaid($databaseId, $databaseTitle) {
        $message = str_replace(['{databaseId}', '{databaseTitle}'], [$databaseId, $databaseTitle], $this->freeToPaidDatabase);

        $subject = "DBIS: Frei verfügbare Datenbank wird lizenzpflichtig - $databaseTitle | DBIS: Freely available database becomes subject to license - $databaseTitle";

        $this->sendMail($subject, $message);
    }

    public function informDatabaseChangedFromPaidToFree($databaseId, $databaseTitle) {
        $message = str_replace(['{databaseId}', '{databaseTitle}'], [$databaseId, $databaseTitle], $this->paidToFreeDatabase);

        $subject = "DBIS: Lizenzpflichtige Datenbank wird frei verfügbar - $databaseTitle | DBIS: Database subject to license becomes freely available - $databaseTitle";
        
        $this->sendMail($subject, $message);
    }

    public function informNewFreeDatabase($databaseId, $databaseTitle, $subjectsString, $typesString) {
        $message = str_replace(['{databaseId}', '{databaseTitle}', '{databaseTypesGerman}', '{databaseSubjectsGerman}', '{databaseTypesEnglish}', '{databaseSubjectsEnglish}'], [$databaseId, $databaseTitle, $typesString["de"], $subjectsString["de"], $typesString["en"], $subjectsString["en"]], $this->newFreeDatabase);

        $subject = "DBIS: Neue frei verfügbare Datenbank wurde eingetragen - $databaseTitle | DBIS: New freely available database has been registered - $databaseTitle";

        // Is only executed via cronjob 
        $this->sendMail($subject, $message);
    }

    private function sendMail($subject, $message) {
        $to = 'technik.dbis@ur.de';
        
        if ($this->isProductive) {
            $to = "ur-ub-dbis-update@listserv.dfn.de";
        } 

        $headers = 'From: nobody@dbis.uni-regensburg.de' . "\r\n" .
                'MIME-Version: 1.0' . "\r\n" .
                'Content-type: text/plain; charset=UTF-8' . "\r\n" .
                'Reply-To: Info.Dbis@bibliothek.uni-regensburg.de' . "\r\n" .
                'X-Mailer: PHP/' . phpversion();


        $success = mail($to, $subject, $message, $headers);

        if ($success) {

        } else {

        }
    }
}