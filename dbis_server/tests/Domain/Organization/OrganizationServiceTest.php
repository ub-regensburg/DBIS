<?php

declare(strict_types=1);

namespace Test\Resources;

use PHPUnit\Framework\TestCase;
use DI\ContainerBuilder;
use App\Domain\Organizations\OrganizationService;
use App\Domain\Organizations\Entities\Organization;
use App\Domain\Organizations\Entities\ExternalOrganizationIdentifier;
use App\Domain\Organizations\Entities\ExternalOrganizationIdentifierNamespace;
use App\Domain\Organizations\Entities\DbisView;
use TypeError;
use App\Domain\Shared\Exceptions\EmailAdressInvalidException;
use App\Domain\Shared\Exceptions\UrlInvalidException;
use App\Domain\Organizations\Exceptions\OrganizationWithUbrIdExistingException;
use App\Domain\Organizations\Exceptions\OrganizationWithDbisIdExistingException;
use App\Domain\Organizations\Exceptions\OrganizationWithUbrIdNotExistingException;
use App\Domain\Organizations\Exceptions\OrganizationWithUbrIdTakenException;
use App\Domain\Organizations\Exceptions\OrganizationWithIpNotExistingException;
use Exception;

final class OrganizationServiceTest extends TestCase
{
    /** @var OrganizationService */
    protected static $service;

    public static function setUpBeforeClass(): void
    {
        // build container and get service from container
        require_once __DIR__ . '/../../../config/DotEnv.php';
        loadDotEnv("/var/www/.env");
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions(__DIR__ . '/../../../config/container.php');
        $container = $containerBuilder->build();
        static::$service = $container->get(OrganizationService::class);
    }

    // taken from https://stackoverflow.com/questions/4356289/php-random-string-generator
    private function getRandomString(int $length = 4)
    {
        return substr(str_shuffle(str_repeat(
            $x = '0123456789abcdefghijklmnoqrst'
                . 'uvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            (int)ceil($length / strlen($x))
        )), 1, $length);
    }

    private function createValidOrganization(string $id = null): Organization
    {
        // these are all required fields for creating a valid organization
        if ($id == null) {
            $id = $this->getRandomString();
        }
        $org = new Organization(
            $id,
            [
            "de" => "Testbibliothek",
            "en" => "Test Library"
                ],
            "de"
        );
        $org->setCity([
            "de" => "Regensburg",
            "en" => "Ratisbon"
        ]);
        $org->setZipcode("93053");
        return $org;
    }

    #
    #
    # Control Tests
    #
    # Correct behavior, that should perform successfully
    public function testCreateValidOrganization(): void
    {
        $this->expectNotToPerformAssertions();
        static::$service->createOrganization($this->createValidOrganization());
    }

    public function testCreateValidOrganizationWithExternalIds(): void
    {
        $this->expectNotToPerformAssertions();
        $org = $this->createValidOrganization();
        $org->setExternalIds([
            new ExternalOrganizationIdentifier(
                $this->getRandomString(),
                new ExternalOrganizationIdentifierNamespace("zdb")
            ),
            new ExternalOrganizationIdentifier(
                $this->getRandomString(),
                new ExternalOrganizationIdentifierNamespace("sigel")
            ),
        ]);
        static::$service->createOrganization($org);
    }

    public function testErrorOnInvalidExtIdNamespace(): void
    {
        # this may be unspecific, but since the error cannot be (easily) triggered
        # through ui, it suffices, that there is an error thrown at all.
        $this->expectException(Exception::class);
        $org = $this->createValidOrganization();
        $org->setExternalIds([
            new ExternalOrganizationIdentifier(
                $this->getRandomString(),
                new ExternalOrganizationIdentifierNamespace("thisisnotavalidns")
            )
        ]);
        static::$service->createOrganization($org);
    }

    public function testUpdateValidOrganization(): void
    {
        $this->expectNotToPerformAssertions();
        $org = $this->createValidOrganization();
        static::$service->createOrganization($org);
        $org->setCity([
            "de" => "Valide Teststadt",
            "en" => "Valid test city"
        ]);
        static::$service->updateOrganization($org);
    }

    public function testDeleteValidOrganization(): void
    {
        $this->expectNotToPerformAssertions();
        $org = $this->createValidOrganization();
        static::$service->createOrganization($org);
        static::$service->deleteOrganizationByUbrId($org->getUbrId());
    }

    public function testGetAllOrganizations(): void
    {
        $this->expectNotToPerformAssertions();
        static::$service->getOrganizations();
    }

    public function testGetAllOrganizationsWithSearchword(): void
    {
        $this->expectNotToPerformAssertions();
        static::$service->getOrganizations([
            "q" => "TEST"
        ]);
    }

    public function testGetAllOrganizationsWithCertainId(): void
    {
        $id = $this->getRandomString();
        $id2 = $this->getRandomString();
        $org = $this->createValidOrganization($id);
        $org2 = $this->createValidOrganization($id2);
        static::$service->createOrganization($org);
        static::$service->createOrganization($org2);
        $results  = static::$service->getOrganizations([
           "ids" => [$id, $id2]
        ]);
        $this->assertCount(2, $results);
    }

    public function testCreateAndGetOrganization(): void
    {
        $this->expectNotToPerformAssertions();
        $org = $this->createValidOrganization();
        static::$service->createOrganization($org);
        $result = static::$service->getOrganizationByUbrId($org->getUbrId());
    }

    public function testCreateDbisViewToOrganization(): void
    {
        $id = $this->getRandomString();
        $org = $this->createValidOrganization($id);
        $org->setDbisView(new DbisView([]));
        static::$service->createOrganization($org);
        $result = static::$service->getOrganizationByUbrId($id);
        $this->assertNotNull($result->getDbisView());
    }

    public function testUpdateDbisViewWithOrganization(): void
    {
        $id = $this->getRandomString();
        $org = $this->createValidOrganization($id);
        static::$service->createOrganization($org);
        $org->setDbisView(new DbisView([]));
        static::$service->updateOrganization($org);
        $result = static::$service->getOrganizationByUbrId($id);
        $this->assertNotNull($result->getDbisView());
    }

    public function testAddDbisViewToOrganization(): void
    {
        $id = $this->getRandomString();
        $org = $this->createValidOrganization($id);
        static::$service->createOrganization($org);
        static::$service->addDbisViewToOrganization($org);
        $result = static::$service->getOrganizationByUbrId($id);
        $this->assertNotNull($result->getDbisView());
    }

    public function testDeleteDbisViewFromOrganization(): void
    {
        $id = $this->getRandomString();
        $org = $this->createValidOrganization($id);
        $org->setDbisView(new DbisView([]));
        static::$service->createOrganization($org);
        static::$service->deleteDbisViewFromOrganization($org);
        $result = static::$service->getOrganizationByUbrId($id);
        $this->assertNull($result->getDbisView());
    }

    public function testGetOrganizationByIp(): void
    {
        $ip = ""; // Removed for security reasons
        $organization = static::$service->getOrganizationByIp($ip);
        $this->assertNotNull($organization);
    }

    #
    #
    # True Positive Tests
    #
    # Expects to throw errors
    public function testErrorOnOrganizationWithUbrIdMissing(): void
    {
        $this->expectException(TypeError::class);
        $org = new Organization(
            null,
            [
            "de" => "Universitätsbibliothek Regensburg",
            "en" => "University Library of Regensburg"
                ],
            "de"
        );
        $org->setCity([
            "de" => "Regensburg",
            "en" => "Ratisbon"
        ]);
        static::$service->createOrganization($org);
    }

    public function testErrorOnZipcodeMssing(): void
    {
        $this->expectException(TypeError::class);
        $org = $this->createValidOrganization();
        $org->setZipcode(null);
        static::$service->createOrganization($org);
    }

    public function testErrorOnCityMissing(): void
    {
        $this->expectException(TypeError::class);
        $org = $this->createValidOrganization();
        $org->setCity(null);
        static::$service->createOrganization($org);
    }

    public function testErrorOnInvalidEmail(): void
    {
        $this->expectException(EmailAdressInvalidException::class);
        $org = $this->createValidOrganization();
        $org->setContact("lisette.mullere");
        static::$service->createOrganization($org);
    }

    public function testErrorOnInvalidHomepageUrl(): void
    {
        $this->expectException(UrlInvalidException::class);
        $org = $this->createValidOrganization();
        $org->setHomepage([
            "de" => "abc.",
            "en" => "abc."
        ]);
        static::$service->createOrganization($org);
    }

    public function testErrorOnOrganizationWithExistingUbrId(): void
    {
        $this->expectException(OrganizationWithUbrIdExistingException::class);
        $id = $this->getRandomString();
        $org = $this->createValidOrganization($id);
        static::$service->createOrganization($org);
        static::$service->createOrganization($org);
    }

    public function testErrorOnOrganizationWithExistingDbisId(): void
    {
        $this->expectException(OrganizationWithDbisIdExistingException::class);
        $id = $this->getRandomString();
        $org1 = $this->createValidOrganization();
        $org1->setDbisId($id);
        static::$service->createOrganization($org1);
        $org2 = $this->createValidOrganization();
        $org2->setDbisId($id);
        static::$service->createOrganization($org2);
    }

    public function testErrorOnUpdatingNonexistentOrganization(): void
    {
        $this->expectException(OrganizationWithUbrIdNotExistingException::class);
        $id = $this->getRandomString();
        $org = $this->createValidOrganization($id);
        static::$service->updateOrganization($org);
    }

    public function testErrorOnDeletingNonexistentOrganization(): void
    {
        $this->expectException(OrganizationWithUbrIdNotExistingException::class);
        $id = $this->getRandomString();
        static::$service->deleteOrganizationByUbrId($id);
    }

    public function testErrorOnAccessingDeletedOrganization(): void
    {
        $this->expectException(OrganizationWithUbrIdNotExistingException::class);
        $id = $this->getRandomString();
        $org = $this->createValidOrganization($id);
        static::$service->createOrganization($org);
        static::$service->deleteOrganizationByUbrId($id);
        static::$service->getOrganizationByUbrId($id);
    }

    public function testErrorOnUpdatingDeletedOrganization(): void
    {
        $this->expectException(OrganizationWithUbrIdNotExistingException::class);
        $id = $this->getRandomString();
        $org = $this->createValidOrganization($id);
        static::$service->createOrganization($org);
        static::$service->deleteOrganizationByUbrId($id);
        static::$service->updateOrganization($org);
    }

    public function testErrorOnCreatingDeletedOrganizationWithSameUbrId(): void
    {
        $this->expectException(OrganizationWithUbrIdTakenException::class);
        $id = $this->getRandomString();
        $org = $this->createValidOrganization($id);
        static::$service->createOrganization($org);
        static::$service->deleteOrganizationByUbrId($id);
        static::$service->createOrganization($org);
    }

    public function testErrorOnOrganizationWithIpNotExisting(): void
    {
        $this->expectException(OrganizationWithIpNotExistingException::class);
        $ip = "1.123445.123.2";
        static::$service->getOrganizationByIp($ip);
    }
}
