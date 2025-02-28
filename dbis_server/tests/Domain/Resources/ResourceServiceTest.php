<?php

declare(strict_types=1);

namespace Test\Resources;

use App\Domain\Resources\Entities\Collection;
use App\Domain\Resources\Entities\SortType;
use App\Domain\Resources\Exceptions\CollectionNotFoundException;
use PHPUnit\Framework\TestCase;
use App\Domain\Resources\ResourceService as ResourceService;
use App\Domain\Resources\Entities\Resource;
use App\Domain\Resources\Entities\Type;
use App\Domain\Resources\Entities\License;
use App\Domain\Resources\Entities\LicenseType;
use App\Domain\Resources\Entities\LicenseForm;
use App\Domain\Resources\Entities\Access;
use App\Domain\Resources\Entities\Host;
use App\Domain\Resources\Entities\TopResourceEntry;
use App\Domain\Resources\Entities\AccessType;
use App\Domain\Resources\Exceptions\ResourceNotFoundException;
use App\Domain\Resources\Exceptions\LicenseNotFoundException;
use App\Domain\Resources\Entities\Keyword;
use App\Domain\Resources\Entities\Subject;
use App\Domain\Resources\Entities\UpdateFrequency;
use App\Domain\Resources\Entities\AlternativeTitle;
use App\Domain\Resources\Entities\Author;
use DI\ContainerBuilder;

final class ResourceServiceTest extends TestCase
{
    /** @var ResourceService */
    public static $service;

    public static function setUpBeforeClass(): void
    {
        // build container and get service from container
        require_once __DIR__ . '/../../../config/DotEnv.php';
        loadDotEnv("/var/www/.env");
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions(__DIR__ . '/../../../config/container.php');
        $container = $containerBuilder->build();
        static::$service = $container->get(ResourceService::class);
    }

    # This is just a very, very basic example. Add any new Tests here!

    public function testCanInstantiateResourceService(): void
    {
        $this->assertInstanceOf(
            ResourceService::class,
            static::$service
        );
    }

    public function testCanCreateValidResource(): void
    {
        $res = $this->getValidResource();
        $id = static::$service->createResource($res);
        $resource = static::$service->getResourceById($id);
        $this->assertInstanceOf(Resource::class, $resource);
    }

    public function testCanUpdateValidResource(): void
    {
        $titleToChange = [
            "de" => "Geänderter Titel!",
            "en" => "Changed title!"
        ];
        $res = $this->getValidResource();
        $id = static::$service->createResource($res);
        $resource = static::$service->getResourceById($id);
        $resource->setTitle($titleToChange);
        static::$service->updateResource($resource);
        ;
        $updatedResource = static::$service->getResourceById($id);
        $this->assertEquals(
            json_encode($updatedResource->getTitle()),
            json_encode($titleToChange)
        );
    }

    public function testCanFetchResourceTypes(): void
    {
        $types = static::$service->getTypes();
        $this->assertContainsOnlyInstancesOf(Type::class, $types);
    }

    public function testCanFetchKeywords(): void
    {
        $types = static::$service->getKeywords();
        $this->assertContainsOnlyInstancesOf(Keyword::class, $types);
    }

    public function testCanFetchSubjects(): void
    {
        $subjects = static::$service->getSubjects();
        $this->assertContainsOnlyInstancesOf(Subject::class, $subjects);
    }

    public function testCanFetchSubjectById(): void
    {
        $subject = static::$service->getSubjectById(1);
        $this->assertNotNull($subject);
        $this->assertInstanceOf(Subject::class, $subject);
    }

    public function testCanFetchUpdateFrequencies(): void
    {
        $uFs = static::$service->getUpdateFrequencies();
        $this->assertContainsOnlyInstancesOf(UpdateFrequency::class, $uFs);
    }

    public function testCanCreateValidResourceWithLicense(): void
    {
        $this->expectNotToPerformAssertions();
        $res = $this->getValidResource();
        $res = $this->addLicenseToResource($res);
        static::$service->createResource($res, [], [], []);
    }

    public function testCanAppendLicenseToResource(): void
    {
        $res = $this->getValidResource();
        $id = static::$service->createResource($res, [], [], []);
        $resource = static::$service->getResourceById((int) $id);
        $license = new License(static::$service->getLicenseTypes()[0]);
        static::$service->addLicenseToResource($resource, $license);
        $resource = static::$service->getResourceById((int) $id);
        $this->assertGreaterThan(0, count($resource->getLicenses()));
    }

    public function testCanDeleteLicenseFromResource(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLicenseToResource($res);
        $id = static::$service->createResource($res, [], [], []);
        $resource = static::$service->getResourceById((int) $id);
        $license = static::$service->getResourceById((int) $id)->getLicenses()[0];
        static::$service->removeLicenseFromResource($resource, $license);
        $resource = static::$service->getResourceById((int) $id);
        $this->assertLessThan(1, count($resource->getLicenses()));
    }

    public function testCanUpdateLicenseTypes(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLicenseToResource($res);
        $oldLicense = $res->getLicenses()[0];
        $id = static::$service->createResource($res, [], [], []);
        $resource = static::$service->getResourceById((int) $id);
        $this->assertEquals(1, $oldLicense->getType()->getId());
        $license = static::$service->getResourceById((int) $id)->getLicenses()[0];
        $license->setType(new LicenseType(2, [], []));
        static::$service->updateLicenseInResource($resource, $license);
        $newLicense = static::$service->getResourceById((int) $id)->getLicenses()[0];
        $this->assertEquals(2, $newLicense->getType()->getId());
    }

    public function testCanLoadLicenseTypes(): void
    {
        $licenseTypes = static::$service->getLicenseTypes();
        $this->assertContainsOnlyInstancesOf(LicenseType::class, $licenseTypes);
        $this->assertGreaterThan(0, count($licenseTypes));
    }

    public function testCanLoadLicenseForm(): void
    {
        $licenseForms = static::$service->getLicenseForms();
        $this->assertContainsOnlyInstancesOf(LicenseForm::class, $licenseForms);
        $this->assertGreaterThan(0, count($licenseForms));
    }

    public function testCanLoadAccessTypes(): void
    {
        $accessTypes = static::$service->getAccessTypes();
        $this->assertContainsOnlyInstancesOf(AccessType::class, $accessTypes);
    }

    public function testCanLoadHosts(): void
    {
        $hosts = static::$service->getHosts();
        $this->assertContainsOnlyInstancesOf(Host::class, $hosts);
    }

    public function testCanAccessResourceByIdWithoutOrganization(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLicenseToResource($res);
        $id = static::$service->createResource($res, [], [], []);
        $resource = static::$service->getResourceById((int) $id);
        $this->assertInstanceOf(Resource::class, $resource);
        $this->assertNotNull($resource);
    }

    public function testCanAccessResourceByIdForOrganization(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLocalLicenseToResource($res, "TEST");
        $id = static::$service->createResource($res, [], [], []);
        $resource = static::$service->getResourceById((int) $id, "TEST");
        $this->assertInstanceOf(Resource::class, $resource);
        $this->assertNotNull($resource);
    }

    public function testResourceNotFoundExceptionOnNonexistentId(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $resource = static::$service->getResourceById(48151623);
    }

    public function testResourceNotFoundExceptionOnNonexistentIdWithLocalAcess(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $resource = static::$service->getResourceById(48151623, "TEST");
    }

    //
    //
    // Update licenses tests

    public function testCanUpdateExistingGlobalLicense(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLicenseToResource($res);
        $id = static::$service->createResource($res, [], [], []);
        $resource1 = static::$service->getResourceById((int) $id);
        $this->assertEquals(1, count($resource1->getLicenses()));
        static::$service->updateResource($resource1);
        $resource2 = static::$service->getResourceById((int) $id);
        $this->assertEquals(1, count($resource2->getLicenses()));
    }

    public function testCanUpdateExistingLocalLicense(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLocalLicenseToResource($res, "TEST");
        $res = $this->addLicenseToResource($res);
        $id = static::$service->createResource($res, [], [], []);
        $resource1 = static::$service->getResourceById((int) $id, "TEST");
        $this->assertEquals(2, count($resource1->getLicenses()));
        static::$service->updateResource($resource1, "TEST");
        $resource2 = static::$service->getResourceById((int) $id, "TEST");
        $this->assertEquals(2, count($resource2->getLicenses()));
    }


    public function testCanAddAccess(): void
    {
        $res = $this->getValidResource();
        $license = new License(new LicenseType(1, []));
        $res->setLicenses([$license]);
        $access = new Access(new AccessType(1, []));
        $license->setAccesses([$access]);
        $id = static::$service->createResource($res);
        $storedRes = static::$service->getResourceById($id);
        $this->assertEquals(1, count($storedRes->getLicenses()[0]->getAccesses()));
    }

    public function testCreatesNewHostOnCreate(): void
    {
        $numHostsBefore = count(static::$service->getHosts());
        $res = $this->getValidResource();
        $license = new License(new LicenseType(1, []));
        $res->setLicenses([$license]);
        $access = new Access(new AccessType(1, []));
        $host = new Host();
        $host->setTitle(["de" => "asdas", "en" => "asdasdad"]);
        $access->setHost($host);
        $license->setAccesses([$access]);
        $id = static::$service->createResource($res);
        $resStored = static::$service->getResourceById($id);
        $numHosts = count(static::$service->getHosts());
        $this->assertEquals($numHostsBefore + 1, $numHosts);
        $this->assertInstanceOf(
            Host::class,
            $resStored->getLicenses()[0]->getAccesses()[0]->getHost()
        );
    }

    public function testCanSetExistingHostOnCreate(): void
    {
        $numHostsBefore = count(static::$service->getHosts());
        $res = $this->getValidResource();
        $license = new License(new LicenseType(1, []));
        $res->setLicenses([$license]);
        $access = new Access(new AccessType(1, []));
        $host = static::$service->getHosts()[0];
        $access->setHost($host);
        $license->setAccesses([$access]);
        $id = static::$service->createResource($res);
        $resCreated = static::$service->getResourceById($id);
        $numHosts = count(static::$service->getHosts());
        $this->assertEquals($numHostsBefore, $numHosts);
        $this->assertInstanceOf(
            Host::class,
            $resCreated->getLicenses()[0]->getAccesses()[0]->getHost()
        );
    }

    public function testCreatesNewHostOnUpdate(): void
    {
        $vRes = $this->getValidResource();
        $id = static::$service->createResource($vRes);
        $res = static::$service->getResourceById($id);
        $numHostsBefore = count(static::$service->getHosts());
        $license = new License(new LicenseType(1, []));
        $res->setLicenses([$license]);
        $access = new Access(new AccessType(1, []));
        $host = new Host();
        $host->setTitle(["de" => "asdas", "en" => "asdasdad"]);
        $access->setHost($host);
        $license->setAccesses([$access]);
        static::$service->updateResource($res);
        $resCreated = static::$service->getResourceById($id);
        $numHosts = count(static::$service->getHosts());
        $this->assertEquals($numHostsBefore + 1, $numHosts);
        $this->assertInstanceOf(
            Host::class,
            $resCreated->getLicenses()[0]
                    ->getAccesses()[0]
            ->getHost()
        );
    }

    public function testCanSetExistingHostOnUpdate(): void
    {
        $vRes = $this->getValidResource();
        $id = static::$service->createResource($vRes);
        $res = static::$service->getResourceById($id);
        $numHostsBefore = count(static::$service->getHosts());
        $license = new License(new LicenseType(1, []));
        $res->setLicenses([$license]);
        $access = new Access(new AccessType(1, []));
        $host = static::$service->getHosts()[0];
        $access->setHost($host);
        $license->setAccesses([$access]);
        static::$service->updateResource($res);
        $resCreated = static::$service->getResourceById($id);
        $numHosts = count(static::$service->getHosts());
        $this->assertEquals($numHostsBefore, $numHosts);
        $this->assertInstanceOf(
            Host::class,
            $resCreated->getLicenses()[0]
                    ->getAccesses()[0]
            ->getHost()
        );
    }

    public function testLicenseNotFoundExceptionOnUpdatingNoexistentLicenseId(): void
    {
        $this->expectException(LicenseNotFoundException::class);
        $res = $this->getValidResource();
        $res = $this->addLicenseToResource($res);
        $id = static::$service->createResource($res, [], [], []);
        $resource1 = static::$service->getResourceById((int) $id);
        $this->assertEquals(1, count($resource1->getLicenses()));
        $license = new License(new LicenseType(1, [], []));
        $license->setId(48151623);
        $resource1->updateLicense($license);
        static::$service->updateResource($resource1);
    }

    public function testLicenseNotFoundExceptionOnUpdatingNoexistentLocalLicenseId(): void
    {
        $this->expectException(LicenseNotFoundException::class);
        $res = $this->getValidResource();
        $res = $this->addLocalLicenseToResource($res, "TEST");
        $id = static::$service->createResource($res, [], [], []);
        $resource1 = static::$service->getResourceById((int) $id, "TEST");
        $this->assertEquals(1, count($resource1->getLicenses()));
        $license = new License(new LicenseType(1, [], []));
        $license->setId(48151623);
        $resource1->updateLicense($license);
        static::$service->updateResource($resource1, "TEST");
    }

    public function testLicenseNotFoundExceptionOnUpdatingLocalLicenseIdForGlobalView(): void
    {
        // This may need some explanation: If a global user tries to access an
        // existing local license of an organization, the system should not
        // return that license.
        $this->expectException(LicenseNotFoundException::class);
        $res = $this->getValidResource();
        // Add a local license
        $res = $this->addLocalLicenseToResource($res, "TEST");
        $id = static::$service->createResource($res, [], [], []);
        $resourceLocal = static::$service->getResourceById((int) $id, "TEST");
        $this->assertEquals(1, count($resourceLocal->getLicenses()));
        $licenseId = $resourceLocal->getLicenses()[0]->getId();
        // Add another global license
        $this->addLicenseToResource($resourceLocal);

        // Get a global resource
        $resourceGlobal = static::$service->getResourceById((int) $id);

        // try to overwrite the existing license
        $license = new License(new LicenseType(1, [], []));
        $license->setId($licenseId);
        $resourceGlobal->updateLicense($license);
        static::$service->updateResource($resourceGlobal);
    }

    public function testLicenseNotFoundExceptionOnUpdatingLocalLicenseIdForForeignView(): void
    {
        // Same as above, except that the license must not be overwritten by
        // other organizations as well
        $this->expectException(LicenseNotFoundException::class);
        $res = $this->getValidResource();
        // Add a local license
        $res = $this->addLocalLicenseToResource($res, "TEST");
        $id = static::$service->createResource($res, [], [], []);
        $resourceLocal = static::$service->getResourceById((int) $id, "TEST");
        $this->assertEquals(1, count($resourceLocal->getLicenses()));
        $licenseId = $resourceLocal->getLicenses()[0]->getId();
        // Add another global license
        $this->addLicenseToResource($resourceLocal);

        // Get a global resource
        $resourceLocal2 = static::$service->getResourceById((int) $id, "OVSH");

        // try to overwrite the existing license
        $license = new License(new LicenseType(1, [], []));
        $license->setId($licenseId);
        $resourceLocal2->updateLicense($license);
        static::$service->updateResource($resourceLocal2);
    }

    //
    //
    // Update licenses tests

    public function testCanDeleteGlobalLicenseFromGlobalView(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLicenseToResource($res);
        $id = static::$service->createResource($res, [], [], []);
        $resource = static::$service->getResourceById((int) $id);
        $this->assertEquals(1, count($resource->getLicenses()));
        $license = $resource->getLicenses()[0];
        static::$service->removeLicenseFromResource($resource, $license);
        $resource = static::$service->getResourceById((int) $id);
        $this->assertEquals(0, count($resource->getLicenses()));
    }

    public function testCanDeleteGlobalLicenseFromLocalView(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLicenseToResource($res);
        $id = static::$service->createResource($res, [], [], []);
        $resource = static::$service->getResourceById((int) $id, "TEST");
        $this->assertEquals(1, count($resource->getLicenses()));
        $license = $resource->getLicenses()[0];
        static::$service->removeLicenseFromResource($resource, $license, "TEST");
        $resource = static::$service->getResourceById((int) $id, "TEST");
        $this->assertEquals(0, count($resource->getLicenses()));
        $resource = static::$service->getResourceById((int) $id);
        $this->assertEquals(0, count($resource->getLicenses()));
    }

    public function testCanDeleteLocalLicenseFromLocalView(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLocalLicenseToResource($res, "TEST");
        $id = static::$service->createResource($res, [], [], []);
        $resource = static::$service->getResourceById((int) $id, "TEST");
        $this->assertEquals(1, count($resource->getLicenses()));
        $license = $resource->getLicenses()[0];
        static::$service->removeLicenseFromResource($resource, $license, "TEST");
        $resource = static::$service->getResourceById((int) $id, "TEST");
        $this->assertEquals(0, count($resource->getLicenses()));
        $resource = static::$service->getResourceById((int) $id);
        $this->assertEquals(0, count($resource->getLicenses()));
    }

    public function testDeletingGlobalLicensesNotInfluencingLocalLicenses(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLocalLicenseToResource($res, "TEST");
        $res = $this->addLicenseToResource($res);
        $id = static::$service->createResource($res, [], [], []);
        $resource = static::$service->getResourceById((int) $id);
        $this->assertEquals(1, count($resource->getLicenses()));
        $license = $resource->getLicenses()[0];
        static::$service->removeLicenseFromResource($resource, $license);
        $resourceLocal = static::$service->getResourceById((int) $id, "TEST");
        $this->assertEquals(1, count($resourceLocal->getLicenses()));
    }

    public function testDeletingForeignLicensesNotInfluencingLocalLicenses(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLocalLicenseToResource($res, "TEST");
        $res = $this->addLocalLicenseToResource($res, "OVSH");
        $id = static::$service->createResource($res, [], [], []);
        $resource = static::$service->getResourceById((int) $id, "OVSH");
        $this->assertEquals(1, count($resource->getLicenses()));
        $license = $resource->getLicenses()[0];
        static::$service->removeLicenseFromResource($resource, $license, "OVSH");
        $resourceLocal = static::$service->getResourceById((int) $id, "TEST");
        $this->assertEquals(1, count($resourceLocal->getLicenses()));
    }

    public function testDeletingGlobalLicenseKeepingOtherLicenses(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLicenseToResource($res);
        $res = $this->addLicenseToResource($res);
        $res = $this->addLocalLicenseToResource($res, "TEST");
        $res = $this->addLocalLicenseToResource($res, "OVSH");
        $id = static::$service->createResource($res, [], [], []);
        $resource = static::$service->getResourceById((int) $id);
        $this->assertEquals(2, count($resource->getLicenses()));
        $license = $resource->getLicenses()[0];
        static::$service->removeLicenseFromResource($resource, $license);
        $resource2 = static::$service->getResourceById((int) $id);
        $this->assertEquals(1, count($resource2->getLicenses()));
        $resourceLocal = static::$service->getResourceById((int) $id, "TEST");
        $this->assertEquals(2, count($resourceLocal->getLicenses()));
        $resourceLocal = static::$service->getResourceById((int) $id, "OVSH");
        $this->assertEquals(2, count($resourceLocal->getLicenses()));
    }

    public function testLicenseNotFoundExceptionOnDeleteLocalLicenseFromGlobalView(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLocalLicenseToResource($res, "TEST");
        $id = static::$service->createResource($res, [], [], []);
        $resource = static::$service->getResourceById((int) $id, "TEST");
        $this->assertEquals(1, count($resource->getLicenses()));
        $license = $resource->getLicenses()[0];
        static::$service->removeLicenseFromResource($resource, $license);
    }

    public function testLicenseNotFoundExceptionOnDeleteLocalLicenseFromForeignView(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLocalLicenseToResource($res, "TEST");
        $id = static::$service->createResource($res, [], [], []);
        $resource = static::$service->getResourceById((int) $id, "TEST");
        $this->assertEquals(1, count($resource->getLicenses()));
        $license = $resource->getLicenses()[0];
        static::$service->removeLicenseFromResource($resource, $license, "OVSH");
    }

    public function testCanFetchAllResources(): void
    {
        $result = static::$service->getResources();
        $this->assertContainsOnlyInstancesOf(Resource::class, $result);
    }

    public function testExactSearch(): void
    {
        $result = static::$service->getResources([
            "q" => "lacrosse"
        ]);
        foreach ($result as $resource) {
            $this->assertStringContainsString("lacrosse", json_encode($resource->toAssocArray()));
        }
    }

    public function testFilterBySubject(): void
    {
        $subject = self::$service->getSubjects()[0];
        $results = static::$service->getResources([
            "filters" => [
                "subjects" => [
                    $subject->getId()
                ]
            ]
        ]);
        foreach ($results as $resource) {
            $subjectIds = array_map(function ($i) {
                return $i->getId();
            }, $resource->getSubjects());
            $this->assertTrue(in_array($subject->getId(), $subjectIds));
        }
    }

    public function testFilterBySubjects(): void
    {
        $subject = self::$service->getSubjects()[0];
        $subject2 = self::$service->getSubjects()[1];
        $results = static::$service->getResources([
            "filters" => [
                "subjects" => [
                    $subject->getId(),
                    $subject2->getId()
                ]
            ]
        ]);
        foreach ($results as $resource) {
            $subjectIds = array_map(function ($i) {
                return $i->getId();
            }, $resource->getSubjects());
            $this->assertTrue(
                in_array($subject->getId(), $subjectIds) ||
                in_array($subject2->getId(), $subjectIds)
            );
        }
    }

    public function canFetchBySubject(): void
    {
        $resultAll = static::$service->getResources();
        $result = static::$service->getResources([
            "for_subject" => 26
        ]);
        $this->assertLessThan(count($result), count($resultAll));
    }

    public function testPaginatedExactSearch(): void
    {
        $result = static::$service->getResources([
            "q" => "lacrosse",
            "limit" => 25,
            "offset" => 0
        ]);
        foreach ($result as $resource) {
            $this->assertStringContainsString("lacrosse", json_encode($resource->toAssocArray()));
        }
        $this->assertLessThanOrEqual(25, count($result));
    }

    //
    //
    // licenses visibility tests

    public function testCanViewGlobalLicensesFromGlobalView(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLicenseToResource($res);
        $id = static::$service->createResource($res, [], [], []);
        $resource = static::$service->getResourceById((int) $id);
        $this->assertEquals(1, count($resource->getLicenses()));
    }

    public function testCanViewGlobalLicensesFromLocalView(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLicenseToResource($res);
        $id = static::$service->createResource($res, [], [], []);
        $resource = static::$service->getResourceById((int) $id, "TEST");
        $this->assertEquals(1, count($resource->getLicenses()));
    }

    public function testCanViewLocalLicensesFromLocalView(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLocalLicenseToResource($res, "TEST");
        $id = static::$service->createResource($res, [], [], []);
        $resource = static::$service->getResourceById((int) $id, "TEST");
        $this->assertEquals(1, count($resource->getLicenses()));
    }

    public function testCannotViewLocalLicenseFromGlobalView(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLocalLicenseToResource($res, "TEST");
        $id = static::$service->createResource($res, [], [], []);
        $resource = static::$service->getResourceById((int) $id);
        $this->assertEquals(0, count($resource->getLicenses()));
    }

    public function testCannotViewLocalLicenseFromForeignView(): void
    {
        $res = $this->getValidResource();
        $res = $this->addLocalLicenseToResource($res, "TEST");
        $id = static::$service->createResource($res, [], [], []);
        $resource = static::$service->getResourceById((int) $id, "OVSH");
        $this->assertEquals(0, count($resource->getLicenses()));
    }

    public function testCanCreateNewHost(): void
    {
        $randomName = $this->getRandomString();
        $host = $this->buildHostWithDeTitle($randomName);

        $res = $this->getValidResourceWithHost($host);
        $id = static::$service->createResource($res);

        // The hosts should now contain an entry with the new name
        $namesInAllHosts = array_map(function ($item) {
            return $item->getTitle()["de"];
        }, static::$service->getHosts());
        $this->assertContains($randomName, $namesInAllHosts);
        // The stored resource should contain one access with the correct host
        $storedResource = static::$service->getResourceById($id);
        $storedHost = $storedResource->getLicenses()[0]->getAccesses()[0]->getHost();
        $this->assertEquals($storedHost->getTitle()["de"], $randomName);
        // The service should return the correct host by name
        $host = static::$service->getHostByName($randomName);
        $this->assertNotNull($host);
        $this->assertInstanceOf(Host::class, $host);
        // Same asserts, but with "get by id"
        $hostById = static::$service->getHostById($host->getId());
        $this->assertNotNull($hostById);
        $this->assertInstanceOf(Host::class, $hostById);
        // Test getting by localized name
        $host = static::$service->getHostByName($randomName, "de");
        $this->assertNotNull($host);
        $this->assertInstanceOf(Host::class, $host);
    }

    public function testCanReferenceExistingHost(): void
    {
        $randomName = $this->getRandomString();
        $host = $this->buildHostWithDeTitle($randomName);

        // first create a new host
        $res = $this->getValidResourceWithHost($host);
        $id = static::$service->createResource($res);

        $storedResource = static::$service->getResourceById($id);
        $storedHost = $storedResource->getLicenses()[0]->getAccesses()[0]->getHost();
        $hostId = $storedHost->getId();
        $hostById = static::$service->getHostById($hostId);
        $this->assertNotNull($hostById);
        $this->assertInstanceOf(Host::class, $hostById);

        // then reference a host with the same name
        $res2 = $this->getValidResourceWithHost($host);

        $hosts = static::$service->getHosts();

        $hostsWithName = array_filter($hosts, function ($host) use ($randomName) {
            return $host->getTitle()["de"] == $randomName;
        });

        // creating a host with the same name should not create another entry
        $this->assertEquals(count($hostsWithName), 1);
    }


    public function testCanUpdateHosts(): void
    {
        $randomName = $this->getRandomString();
        $host = $this->buildHostWithDeTitle($randomName);

        $res = $this->getValidResourceWithHost($host);
        $id = static::$service->createResource($res);
        $storedResource = static::$service->getResourceById($id);

        $randomName2 = $this->getRandomString();

        $hostNew = $this->buildHostWithDeTitle($randomName2);
        $access = $storedResource->getLicenses()[0]->getAccesses()[0];

        $access->setHost($hostNew);
        static::$service->updateResource($storedResource);
        $resource = static::$service->getResourceById($id);

        $titleDe = $storedResource->getLicenses()[0]->getAccesses()[0]->getHost()->getTitle()["de"];
        $this->assertEquals($titleDe, $randomName2);
    }

    public function testCanDeleteHost(): void
    {
        $randomName = $this->getRandomString();
        $host = $this->buildHostWithDeTitle($randomName);
        $res = $this->getValidResourceWithHost($host);
        $id = static::$service->createResource($res);
        $storedResource = static::$service->getResourceById($id);

        $access = $storedResource->getLicenses()[0]->getAccesses()[0];
        $access->setHost(null);
        static::$service->updateResource($storedResource);
        $resource = static::$service->getResourceById($id);

        $host = $storedResource->getLicenses()[0]->getAccesses()[0]->getHost();
        $this->assertNull($host);
    }


    public function testCanAddAlternativeTitle()
    {
        $randomTitle = $this->getRandomString();
        $res = $this->getValidResource();
        $id = static::$service->createResource($res);
        $storedResource = static::$service->getResourceById($id);
        $altTitles = $storedResource->getAlternativeTitles();
        $altTitlesNew = array_merge(
            $altTitles,
            [new AlternativeTitle(["de" => $randomTitle, "en" => $randomTitle])]
        );
        $storedResource->setAlternativeTitles($altTitlesNew);

        static::$service->updateResource($storedResource);
        $storedResourceNew = static::$service->getResourceById($id);

        $altTitlesStoredAndNew = $storedResourceNew->getAlternativeTitles();

        $this->assertEquals(count($altTitles) + 1, count($altTitlesStoredAndNew));
        $this->assertEquals($randomTitle, end($altTitlesStoredAndNew)->getTitle()["de"]);
        $this->assertEquals($randomTitle, end($altTitlesStoredAndNew)->getTitle()["en"]);
    }

    public function testCanChangeAlternativeTitle()
    {
        $randomTitle = $this->getRandomString();
        $res = $this->getValidResource();
        $id = static::$service->createResource($res);
        $storedResource = static::$service->getResourceById($id);
        $altTitles = $storedResource->getAlternativeTitles();

        array_pop($altTitles);

        $altTitlesNew = array_merge(
            $altTitles,
            [new AlternativeTitle(["de" => $randomTitle, "en" => $randomTitle])]
        );
        $storedResource->setAlternativeTitles($altTitlesNew);

        static::$service->updateResource($storedResource);
        $storedResourceNew = static::$service->getResourceById($id);

        $altTitlesStoredAndNew = $storedResourceNew->getAlternativeTitles();

        $this->assertEquals(count($altTitles) + 1, count($altTitlesStoredAndNew));
        $this->assertEquals($randomTitle, end($altTitlesStoredAndNew)->getTitle()["de"]);
        $this->assertEquals($randomTitle, end($altTitlesStoredAndNew)->getTitle()["en"]);
    }

    public function testCanClearAlternativeTitle()
    {
        $randomTitle = $this->getRandomString();
        $res = $this->getValidResource();
        $id = static::$service->createResource($res);
        $storedResource = static::$service->getResourceById($id);
        $altTitles = $storedResource->getAlternativeTitles();

        $storedResource->setAlternativeTitles([]);

        static::$service->updateResource($storedResource);
        $storedResourceNew = static::$service->getResourceById($id);

        $altTitlesStoredAndNew = $storedResourceNew->getAlternativeTitles();

        $this->assertEquals(0, count($altTitlesStoredAndNew));
    }


    public function testCanSetTopResource(): void
    {
        $resource = $this->getValidResource();
        $id = static::$service->createResource($resource);
        $storedResource = static::$service->getResourceById($id);
        $topresentries = [
            new TopResourceEntry(
                "TEST",
                $id,
                static::$service->getSubjects()[0]
            )
        ];
        $storedResource->setTopResourceEntries($topresentries);
        static::$service->updateResource($storedResource, "TEST");
        $updatedResource = static::$service->getResourceById($id, "TEST");
        $this->assertEquals(count($updatedResource->getTopResourceEntries()), 1);
        // Test other "comfort"-function
        $updatedResource->setTopEntryFor(static::$service->getSubjects()[1], 0, "TEST");
        static::$service->updateResource($updatedResource, "TEST");
        $updatedResource = static::$service->getResourceById($id, "TEST");
        $this->assertEquals(count($updatedResource->getTopResourceEntries()), 2);
    }

    public function testCanRemoveTopResource(): void
    {
        $resource = $this->getValidResource();
        $id = static::$service->createResource($resource);
        $storedResource = static::$service->getResourceById($id);
        $topresentries = [
            new TopResourceEntry(
                "TEST",
                $id,
                static::$service->getSubjects()[0]
            )
        ];
        $storedResource->setTopResourceEntries($topresentries);
        static::$service->updateResource($storedResource, "TEST");
        $updatedResource = static::$service->getResourceById($id, "TEST");
        $this->assertEquals(count($updatedResource->getTopResourceEntries()), 1);
        $updatedResource->removeTopEntryFor(
            static::$service->getSubjects()[0],
            "TEST"
        );
        static::$service->updateResource($resource, "TEST");
        $updatedResource = static::$service->getResourceById($id, "TEST");
        $this->assertEquals(count($updatedResource->getTopResourceEntries()), 0);
    }

    public function testCanClearTopResourcesForSubject(): void
    {
        $resource = $this->getValidResource();
        $id = static::$service->createResource($resource);
        $storedResource = static::$service->getResourceById($id);
        $topresentries = [
            new TopResourceEntry(
                "TEST",
                $id,
                static::$service->getSubjects()[0]
            ),
            new TopResourceEntry(
                "ACEL",
                $id,
                static::$service->getSubjects()[0]
            )
        ];
        $storedResource->setTopResourceEntries($topresentries);
        static::$service->updateResource($storedResource, "TEST");
        $updatedResource = static::$service->getResourceById($id, "TEST");
        $this->assertEquals(count($updatedResource->getTopResourceEntries()), 1);
        static::$service->clearTopResourceEntriesForSubject(
            static::$service->getSubjects()[0],
            "TEST"
        );

        $updatedResourceSeenByTEST = static::$service->getResourceById($id, "TEST");
        $this->assertEquals(count($updatedResourceSeenByTEST->getTopResourceEntries()), 0);

        // Also check, that entries of other orgs remain untouched by removal
        $updatedResourceSeenByACEL = static::$service->getResourceById($id, "ACEL");
        $this->assertEquals(count($updatedResourceSeenByACEL->getTopResourceEntries()), 1);
    }

    public function testCannotAccessTopResourceOfOtherOrganization(): void
    {
        $resource = $this->getValidResource();
        $id = static::$service->createResource($resource);
        $storedResource = static::$service->getResourceById($id);
        $topresentries = [
            new TopResourceEntry(
                "TEST",
                $id,
                static::$service->getSubjects()[0]
            )
        ];
        $storedResource->setTopResourceEntries($topresentries);
        static::$service->updateResource($storedResource, "TEST");
        $updatedResource = static::$service->getResourceById($id, "TEST");
        $this->assertEquals(count($updatedResource->getTopResourceEntries()), 1);

        $updatedResourceSeenByGlobal = static::$service->getResourceById($id);
        $this->assertEquals(count($updatedResourceSeenByGlobal->getTopResourceEntries()), 0);

        $updatedResourceSeenByOtherOrg = static::$service->getResourceById($id, "ACEL");
        $this->assertEquals(count($updatedResourceSeenByOtherOrg->getTopResourceEntries()), 0);
    }

    public function testCanSaveTopResourceOrder(): void
    {
        $ubrId = "TEST";
        $subject = ResourceServiceTest::$service->getSubjects()[0];

        function createResourceWithTopResourceEntry($that, $ubrId, int $sortOrder = 0)
        {
            $r = $that->getValidResource();
            $subject = ResourceServiceTest::$service->getSubjects()[0];
            $id = ResourceServiceTest::$service->createResource($r);
            $savedR = ResourceServiceTest::$service->getResourceById($id);
            $topResEntry = new TopResourceEntry($ubrId, $id, $subject);
            $topResEntry->setOrder($sortOrder);
            $savedR->setTopResourceEntries([$topResEntry]);
            $updatedR = ResourceServiceTest::$service->updateResource($savedR, $ubrId);
            return ResourceServiceTest::$service->getResourceById($id, $ubrId);
            ;
        }

        function setOrder(Resource $resource, Subject $subject, int $sortOrder, string $ubrId): Resource
        {
            $resource->setTopEntryFor($subject, $sortOrder, $ubrId);
            ResourceServiceTest::$service->updateResource($resource, $ubrId);
            return ResourceServiceTest::$service->getResourceById($resource->getId(), $ubrId);
        }

        /** @var resourceA Resource*/
        $resourceA = createResourceWithTopResourceEntry($this, $ubrId);
        /** @var resourceB Resource*/
        $resourceB = createResourceWithTopResourceEntry($this, $ubrId, 1);
        $this->assertEquals($resourceA->getTopResourceEntryForSubject($subject)->getOrder(), 0);
        $this->assertEquals($resourceB->getTopResourceEntryForSubject($subject)->getOrder(), 1);

        $resourceAUpdated = setOrder($resourceA, $subject, 1, $ubrId);
        $resourceBUpdated = setOrder($resourceA, $subject, 0, $ubrId);

        $this->assertEquals($resourceAUpdated->getTopResourceEntryForSubject($subject)->getOrder(), 1);
        $this->assertEquals($resourceBUpdated->getTopResourceEntryForSubject($subject)->getOrder(), 0);
    }

    public function testToleratesNoMatch(): void
    {
        $results = static::$service->getResources([
                        'q' => 'green eggs and ham 152315',
                        'limit' => 25,
                        'offset' => 25 * (1 - 1),
                        'with_total_nr' => true]);
        $this->assertEquals($results["total_nr"], 0);
        $this->assertEquals(count($results["resources"]), 0);
    }

    public function testPaginationSizeReturnsMaximumCountOfItems(): void
    {
        $pagesize = 50;
        $results = static::$service->getResources(
            [
                'q' => 's',
                'limit' => $pagesize,
                'offset' => 0,
                'with_total_nr' => true
            ]
        );
        $this->assertEquals(count($results['resources']), $pagesize);
    }

    public function testPaginationIndexingWorks(): void
    {
        $pagesize = 50;
        $results1 = static::$service->getResources(
            [
                'q' => 's',
                'limit' => $pagesize,
                'offset' => 0,
                'with_total_nr' => true
            ]
        );
        $results2 = static::$service->getResources(
            [
                'q' => 's',
                'limit' => $pagesize,
                'offset' => $pagesize,
                'with_total_nr' => true
            ]
        );
        $ids1 = array_map(function ($item) {
            return $item->getId();
        }, $results1['resources']);
        $ids2 = array_map(function ($item) {
            return $item->getId();
        }, $results2['resources']);

        foreach ($ids1 as $id) {
            // check, if there are any same items
            $this->assertNotContains($id, $ids2);
        }

        $this->assertEquals(count($results1['resources']), $pagesize);
        $this->assertEquals(count($results2['resources']), $pagesize);
    }

    //
    //
    // Collection testing

    public function testCanCreateValidCollection(): void
    {
        $localOrganizationId = 'OVSH';
        $collection = $this->getValidCollection();
        $id = static::$service->createCollection($collection, $localOrganizationId);
        $collection = static::$service->getCollectionById($id, $localOrganizationId);
        $this->assertInstanceOf(Collection::class, $collection);
    }

    public function testCanUpdateCollection(): void
    {
        $titleToChange = [
            "de" => "Geänderter Titel!",
            "en" => "Changed title!"
        ];
        $localOrganizationId = 'OVSH';
        $collection = $this->getValidCollection();
        $id = static::$service->createCollection($collection, $localOrganizationId);

        $existing_collection = null;
        try {
            $existing_collection = static::$service->getCollectionById($id, $localOrganizationId);
        } catch (CollectionNotFoundException $e) {
            $this->fail($e->getMessage());
        }

        $existing_collection->setTitle($titleToChange);
        static::$service->updateCollection($existing_collection, $localOrganizationId);

        $updated_collection = null;
        try {
            $updated_collection = static::$service->getCollectionById($id, $localOrganizationId);
        } catch (CollectionNotFoundException $e) {
            $this->fail($e->getMessage());
        }

        $this->assertEquals(
            json_encode($updated_collection->getTitle()),
            json_encode($titleToChange)
        );
    }

    public function testSubjectFilter()
    {
        $subject1 = 2;
        $resources = static::$service->getResources([
            "filters" => [
                "subjects" => [$subject1]
            ]
        ]);

        foreach ($resources as $r) {
            $subjects = $r->getSubjects();
            $subjectIds = array_map(function ($x) {
                return $x->getId();
            }, $subjects);
            $this->assertContains($subject1, $subjectIds);
        }
    }

    public function testSubjectFilterMultiple()
    {
        $subject1 = 2;
        $subject2 = 6;
        $resources = static::$service->getResources([
            "filters" => [
                "subjects" => [$subject1, $subject2]
            ]
        ]);

        foreach ($resources as $r) {
            $subjects = $r->getSubjects();
            $subjectIds = array_map(function ($x) {
                return $x->getId();
            }, $subjects);
            $this->assertTrue(in_array($subject1, $subjectIds) || in_array($subject2, $subjectIds));
        }
    }

    public function testKeywordFilter()
    {
        // FIXTURE CODE
        // Create a new resource with a keyword appended to it
        $resource = $this->getValidResource();
        $keywords = static::$service->getKeywords(['only_given' => false]);
        $keyword1 = $keywords[0];
        $keyword1Id = $keyword1->getId();
        $resource->setKeywords([$keyword1]);
        static::$service->createResource($resource);
        // TEST CODE
        $resources = static::$service->getResources([
            "filters" => [
                "keyword-ids" => [$keyword1Id]
            ]
        ]);
        foreach ($resources as $r) {
            $kws = $r->getKeywords();
            $kwIds = array_map(function ($x) {
                return $x->getId();
            }, $kws);
            $this->assertContains($keyword1Id, $kwIds);
        }
    }

    public function testKeywordFilterMulti()
    {
        // FIXTURE CODE
        // Create a new resource with a keyword appended to it
        $resource = $this->getValidResource();
        $keywords = static::$service->getKeywords(['only_given' => false]);
        $keyword1 = $keywords[0];
        $keyword2 = $keywords[1];
        $keyword1Id = $keyword1->getId();
        $keyword2Id = $keyword2->getId();
        $resource->setKeywords([$keyword1, $keyword2]);
        static::$service->createResource($resource);
        // TEST CODE
        $resources = static::$service->getResources([
            "filters" => [
                "keyword-ids" => [$keyword1Id, $keyword2Id]
            ]
        ]);
        foreach ($resources as $r) {
            $kws = $r->getKeywords();
            $kwIds = array_map(function ($x) {
                return $x->getId();
            }, $kws);
            $this->assertTrue(in_array($keyword1Id, $kwIds) || in_array($keyword2Id, $kwIds));
        }
    }

    public function testDBTypeFilter()
    {
        $resourceType1 = 1;
        $resources = static::$service->getResources([
            "filters" => [
                "resource-types" => [$resourceType1]
            ]
        ]);
        foreach ($resources as $r) {
            $types = $r->getTypes();
            $typeIds = array_map(function ($x) {
                return $x->getId();
            }, $types);
            $this->assertTrue(in_array($resourceType1, $typeIds));
        }
    }

    public function testDBTypeFilterMulti()
    {
        $resourceType1 = 1;
        $resourceType2 = 2;
        $resources = static::$service->getResources([
            "filters" => [
                "resource-types" => [
                    $resourceType1,
                    $resourceType2]
            ]
        ]);
        foreach ($resources as $r) {
            $types = $r->getTypes();
            $typeIds = array_map(function ($x) {
                return $x->getId();
            }, $types);
            $this->assertTrue(in_array($resourceType1, $typeIds) || in_array($resourceType2, $typeIds));
        }
    }

    public function testAvailabilityFilterFree()
    {
        $filters = [];
        $filters['access'][] = ['license' => 1];
        $resources = static::$service->getResources([
            "filters" => $filters
        ]);
        foreach ($resources as $r) {
            $licenses = $r->getLicenses();
            $licenseIds = array_map(function (License $l) {
                return $l->getType()->getId();
            }, $licenses);
            $this->assertContains(1, $licenseIds);
        }
    }

    public function testAvailabilityFilterOrgAndInternet()
    {
        $orgId = "ACEL";
        $filters = [];
        $filters['access'][] = ['license' => 2];
        $filters['access'][] = ['license' => 3];
        $filters['access'][] = ['license' => 4];
        $resources = static::$service->getResources([
            "filters" => $filters,
            "organizationId" => $orgId
        ]);
        foreach ($resources as $r) {
            $licenses = $r->getLicenses();
            $licenseIds = array_map(function (License $l) {
                return $l->getType()->getId();
            }, $licenses);
            $this->assertTrue(in_array(2, $licenseIds) ||
                    in_array(3, $licenseIds) ||
                    in_array(4, $licenseIds));
        }
    }

    public function testAvailabilityFilterNoAccess()
    {
        $orgId = "ACEL";
        $filters = [];
        $filters['access'][] = ['access' => 'none'];
        $resources = static::$service->getResources([
            "filters" => $filters,
            "organizationId" => $orgId
        ]);
        foreach ($resources as $r) {
            $licenses = $r->getLicenses();
            $this->assertTrue(count($licenses) == 0);
        }
    }


    //
    //
    // REPORT TIME FILTER

    public function testValidFromDateReportTimeFilter()
    {
        $validFromYear = 2000;
        $filters = [];
        $filters['report-time'] = ['start' => $validFromYear];
        $resources = static::$service->getResources([
            "filters" => $filters
        ]);
        foreach ($resources as $r) {
            $year = date('Y', strtotime($r->getReportTimeStart()));
            $this->assertTrue($year >= $validFromYear);
        }
    }

    public function testValidToDateReportTimeFilter()
    {
        $validToYear = 2022;
        $filters = [];
        $filters['report-time'] = ['end' => $validToYear];
        $resources = static::$service->getResources([
            "filters" => $filters
        ]);
        foreach ($resources as $r) {
            $year = date('Y', strtotime($r->getReportTimeEnd()));
            $this->assertTrue($year <= $validToYear);
        }
    }

    public function testValidToAndFromDateReportTimeFilter()
    {
        $validToYear = 2022;
        $validFromYear = 2000;
        $filters = [];
        $filters['report-time'] = [
            'end' => $validToYear,
            'start' => $validFromYear];
        $resources = static::$service->getResources([
            "filters" => $filters
        ]);
        foreach ($resources as $r) {
            $yearStart = date('Y', strtotime($r->getReportTimeStart()));
            $yearEnd = date('Y', strtotime($r->getReportTimeEnd()));
            $this->assertTrue(($yearStart >= $validFromYear) && $yearEnd <= $validToYear);
        }
    }

    //
    //
    // PUBLICATION TIME FILTER

    public function testValidFromDatePublicationTimeFilter()
    {
        $validFromYear = 2000;
        $filters = [];
        $filters['publication-time'] = ['start' => $validFromYear];
        $resources = static::$service->getResources([
            "filters" => $filters
        ]);
        foreach ($resources as $r) {
            $year = date('Y', strtotime($r->getPublicationTimeStart()));
            $this->assertTrue($year >= $validFromYear);
        }
    }

    public function testValidToDatePublicationTimeFilter()
    {
        $validToYear = 2000;
        $filters = [];
        $filters['publication-time'] = ['end' => $validToYear];
        $resources = static::$service->getResources([
            "filters" => $filters
        ]);
        foreach ($resources as $r) {
            $year = date('Y', strtotime($r->getPublicationTimeEnd()));
            $this->assertTrue($year <= $validToYear);
        }
    }

    public function testValidFromAndToDatePublicationTimeFilter()
    {
        $validFromYear = 2020;
        $validToYear = 2021;
        $filters = [];
        $filters['publication-time'] = [
            'end' => $validToYear,
            'start' => $validFromYear];
        $resources = static::$service->getResources([
            "filters" => $filters
        ]);
        foreach ($resources as $r) {
            $yearEnd = date('Y', strtotime($r->getPublicationTimeEnd()));
            $yearStart = date('Y', strtotime($r->getPublicationTimeStart()));
            $this->assertTrue($yearEnd <= $validToYear && $yearStart >= $validFromYear);
        }
    }

    public function testFilterByHosts()
    {
        $host = static::$service->getHosts()[0];
        $resources = static::$service->getResources([
            "filters" => [
                "host-ids" => [$host->getId()]
            ]
        ]);
        foreach ($resources as $r) {
            $containsHost = false;
            foreach ($r->getLicenses() as $l) {
                foreach ($l->getAccesses() as $a) {
                    if ($a->getHost()->getId() == $host->getId()) {
                        $containsHost = true;
                    }
                }
            }
            $this->assertTrue($containsHost);
        }
    }

    public function testFilterByHostsMulti()
    {
        $host = static::$service->getHosts()[0];
        $host2 = static::$service->getHosts()[1];
        $resources = static::$service->getResources([
            "filters" => [
                "host-ids" => [$host->getId(), $host2->getId()]
            ]
        ]);
        foreach ($resources as $r) {
            $containsHost = false;
            foreach ($r->getLicenses() as $l) {
                foreach ($l->getAccesses() as $a) {
                    if ($a->getHost()->getId() == $host->getId() ||
                            $a->getHost()->getId() == $host->getId()
                    ) {
                        $containsHost = true;
                    }
                }
            }
            $this->assertTrue($containsHost);
        }
    }

    public function testFilterByAuthor()
    {
        $author = static::$service->getAuthors()[0];
        $authorId = $author->getId();
        $resources = static::$service->getResources([
            "filters" => [
                "author-ids" => [$authorId]
            ]
        ]);
        foreach ($resources as $r) {
            $authors = $r->getAuthors();
            $authorIds = array_map(function ($x) {
                return $x->getId();
            }, $authors);
            $this->assertContains($authorId, $authorIds);
        }
    }

    public function testFilterByAuthorMulti()
    {
        $author = static::$service->getAuthors()[0];
        $author2 = static::$service->getAuthors()[1];
        $authorId = $author->getId();
        $author2Id = $author2->getId();
        $resources = static::$service->getResources([
            "filters" => [
                "author-ids" => [$authorId, $author2Id]
            ]
        ]);
        foreach ($resources as $r) {
            $authors = $r->getAuthors();
            $authorIds = array_map(function ($x) {
                return $x->getId();
            }, $authors);
            $this->assertTrue(in_array($authorId, $authorIds) ||
                    in_array($author2Id, $authorIds));
        }
    }




    // =========================================================================
    // === BOOTSTRAPPING CODE
    // =========================================================================

    private function buildHostWithDeTitle(string $titleDe): Host
    {
        $host = new Host();
        $host->setTitle([
            "de" => $titleDe,
            "en" => ""
        ]);
        return $host;
    }

    private function getRandomString(): string
    {
        return substr(md5(strval(rand())), 0, 7);
    }

    private function getValidResourceWithHost(Host $host)
    {
        $res = $this->getValidResource();
        $res = $this->addLicenseToResource($res);

        $access = new Access(static::$service->getAccessTypes()[0]);
        $access->setHost($host);
        $res->getLicenses()[0]->setAccesses([$access]);
        return $res;
    }

    private function addLicenseToResource(Resource $r): Resource
    {
        $r->addLicense(
            new License(new LicenseType(1, [], []))
        );
        return $r;
    }

    private function addLocalLicenseToResource(Resource $r, string $organizationId): Resource
    {
        $license = new License(new LicenseType(1, [], []));
        $license->setOrganizationId($organizationId);
        $r->addLicense($license);
        return $r;
    }

    public function getValidResource()
    {
        $types = static::$service->getLicenseTypes();
        $subjects = static::$service->getSubjects();
        $updateFrequencies = static::$service->getUpdateFrequencies();
        $resource = new Resource(
            [
            "de" => "Testressource",
            "en" => "Test Resource"
                ],
            [
                $types[0]
                ]
        );
        $resource->setAuthors([new Author([
            "de" => "DBIS Team",
            "en" => "Team DBIS"
        ])]);
        $resource->setDescription([
            "de" => "Resource zum Testen. Lorem Ipsum dolor sit amet.",
            "en" => "Resource for testing. Lorem Ipsum dolor sit amet."
        ]);
        $resource->setDescriptionShort([
            "de" => "Resource zum Testen. Lorem Ipsum dolor sit amet.",
            "en" => "Resource for testing. Lorem Ipsum dolor sit amet."
        ]);
        $resource->setPublicationTimeStart("2020-10-31");
        $resource->setPublicationTimeEnd("2020-10-31");
        $resource->setReportTimeEnd("2020-10-31");
        $resource->setReportTimeStart("2020-10-31");
        $resource->setAlternativeTitles([
           new AlternativeTitle(["de" => "TEST", "en" => "TEST_EN"]),
           new AlternativeTitle(["de" => "TEST2", "en" => "TEST_EN2"])
        ]);

        $resource->setSubjects([
            $subjects[0],
            $subjects[1]
        ]);
        $resource->setUpdateFrequency($updateFrequencies[0]);
        return $resource;
    }

    private function getValidCollection(): Collection
    {
        $title = array('de' => 'Test-Kollektion', 'en' => 'Test-Collection');
        $collection = new Collection($title);

        $sort_types = static::$service->getSortTypes();
        $sort_type = $sort_types[0];
        $collection->setSortBy($sort_type);

        $resources = static::$service->getResources([
            "limit" => 5,
            "offset" => 0
        ]);
        $resourceIds = array_map(function (Resource $resource) {
            return $resource->getId();
        }, $resources);
        $collection->setResourceIds($resourceIds);

        $collection->setIsVisible(false);
        $collection->setIsSubject(true);

        return $collection;
    }
}
