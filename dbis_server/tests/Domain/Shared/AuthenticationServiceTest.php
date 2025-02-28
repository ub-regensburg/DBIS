<?php

declare(strict_types=1);

namespace Test\Shared;

use PHPUnit\Framework\TestCase;
use DI\ContainerBuilder;
use App\Domain\Shared\AuthService;
use App\Domain\Shared\Entities\User;
use App\Domain\Shared\Entities\Privilege;
use App\Domain\Shared\Entities\PrivilegeType;
use App\Domain\Shared\Exceptions\AuthenticationFailedException;
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
use Exception;

final class AuthenticationServiceTest extends TestCase
{
    /** @var AuthService */
    protected static $service;

    protected static $adminUserName = "Alfred Admin";
    protected static $adminUserPw = "test1234";
    protected static $adminUserMail = "test1234";
    protected static $superAdminUserName = "Silvia Super";
    protected static $superAdminUserPw = "test1234";

    public static function setUpBeforeClass(): void
    {
        // build container and get service from container
        require_once __DIR__ . '/../../../config/DotEnv.php';
        loadDotEnv("/var/www/.env");
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions(__DIR__ . '/../../../config/container.php');
        $container = $containerBuilder->build();
        static::$service = $container->get(AuthService::class);

        AuthenticationServiceTest::loadConfig();
    }

    private static function loadConfig(): void
    {
        $content = file_get_contents(__DIR__ . "/authtest-config.json");
        $config = json_decode($content, true);
        AuthenticationServiceTest::$adminUserName = $config["adminUser"];
        AuthenticationServiceTest::$adminUserPw = $config["adminPw"];
        AuthenticationServiceTest::$adminUserMail = $config["adminMail"];
        AuthenticationServiceTest::$superAdminUserName = $config["superadminUser"];
        AuthenticationServiceTest::$superAdminUserPw = $config["superadminPw"];
    }

    //
    //
    // Control Tests/Sanity Checks
    public function testCanLoginWithValidCredentials(): void
    {
        static::$service->login(static::$adminUserName, static::$adminUserPw);
        $this->assertTrue(static::$service->isSessionAuthenticated());
    }

    public function testCanLoginWithValidCredentialsAndEmail(): void
    {
        static::$service->login(static::$adminUserMail, static::$adminUserPw);
        $this->assertTrue(static::$service->isSessionAuthenticated());
    }

    public function testSuccessfulLogout(): void
    {
        $this->expectNotToPerformAssertions();
        static::$service->logout();
    }

    public function testAuthenticatedUserDefinedAfterLogin(): void
    {
        static::$service->login(static::$adminUserName, static::$adminUserPw);
        $user = static::$service->getAuthenticatedUser();
        $this->assertNotNull($user);
        static::$service->logout();
    }

    public function testNoAuthenticatedUserDefinedAfterLogout(): void
    {
        static::$service->login(static::$adminUserName, static::$adminUserPw);
        static::$service->logout();
        $user = static::$service->getAuthenticatedUser();
        $this->assertNull($user);
    }

    public function testAuthenticatedUserToBeAdmin(): void
    {
        static::$service->login(static::$adminUserName, static::$adminUserPw);
        $result = static::$service->hasAuthenticatedUserRole("admin");
        $this->assertTrue($result);
        $result2 = static::$service->hasAuthenticatedUserRole("superadmin");
        $this->assertFalse($result2);
        static::$service->logout();
    }

    public function testAuthenticatedUserToBeSuperadmin(): void
    {
        static::$service->login(static::$superAdminUserName, static::$superAdminUserPw);
        $result = static::$service->hasAuthenticatedUserRole("admin");
        $this->assertFalse($result);
        $result2 = static::$service->hasAuthenticatedUserRole("superadmin");
        $this->assertTrue($result2);
        static::$service->logout();
    }

    //
    //
    // Test GetUsers

    public function testCanGetUsersAsSuperadmin(): void
    {
        static::$service->login(static::$superAdminUserName, static::$superAdminUserPw);
        $results = static::$service->getUsers();
        $this->assertNotNull($results);
        $this->assertGreaterThan(0, count($results));
        $this->assertContainsOnlyInstancesOf(User::class, $results);
        static::$service->logout();
    }

    public function testCannotGetUsersAsAdmin(): void
    {
        static::$service->login(static::$adminUserName, static::$adminUserPw);
        $results = static::$service->getUsers();
        $this->assertEquals(0, count($results));
        static::$service->logout();
    }

    public function testCannotGetUsersWithoutLogin(): void
    {
        $results = static::$service->getUsers();
        $this->assertEquals(0, count($results));
    }

    //
    //
    // Test GetUserById

    public function testCanGetUserAsSuperadmin(): void
    {
        static::$service->login(static::$superAdminUserName, static::$superAdminUserPw);
        $result = static::$service->getUserById(static::$adminUserName);
        $this->assertNotNull($result);
        $this->assertInstanceOf(User::class, $result);
        static::$service->logout();
    }

    public function testCannotGetUserAsAdmin(): void
    {
        static::$service->login(static::$adminUserName, static::$adminUserPw);
        $result = static::$service->getUserById(static::$adminUserName);
        $this->assertNull($result);
        static::$service->logout();
    }

    public function testCannotGetUserWithoutLogin(): void
    {
        $result = static::$service->getUserById(static::$adminUserName);
        $this->assertNull($result);
    }

    //
    //
    // Test add and remove privileges
    public function testCanAddAndRemovePrivilegeAsSuperadmin(): void
    {
        static::$service->login(static::$superAdminUserName, static::$superAdminUserPw);
        $user = static::$service->getUserById(static::$adminUserName);
        $numPrivileges = count($user->getPrivileges());
        $newPrivilege = new Privilege("admin", "TEST");
        $newPrivilege->setType(static::$service->getPrivilegeTypeById(1));
        $user->addPrivilege($newPrivilege);
        static::$service->persistUser($user);
        $savedUser = static::$service->getUserById(static::$adminUserName);

        $this->assertEquals($numPrivileges + 1, count($savedUser->getPrivileges()));

        $privileges = $savedUser->getPrivileges();
        $lastPrivilege = end($privileges);
        $lastId = $lastPrivilege->getId();
        $savedUser->removePrivilegeById($lastId);
        static::$service->persistUser($savedUser);

        $savedUser2 = static::$service->getUserById(static::$adminUserName);
        $this->assertEquals($numPrivileges, count($savedUser2->getPrivileges()));

        static::$service->logout();
    }

    public function testCannotAddSamePrivilegeTwice(): void
    {
        static::$service->login(static::$superAdminUserName, static::$superAdminUserPw);
        $user = static::$service->getUserById(static::$adminUserName);
        $numPrivileges = count($user->getPrivileges());
        $newPrivilege = new Privilege("admin", "TEST");
        $newPrivilege->setType(static::$service->getPrivilegeTypeById(1));
        $user->addPrivilege($newPrivilege);
        // Add same privilege twice; it should only appear once in privileges
        $user->addPrivilege($newPrivilege);
        $this->assertEquals(count($user->getPrivileges()), $numPrivileges + 1);
        static::$service->logout();
    }

    //
    //
    // True Positives
    public function testCannotLoginWithInvalidCredentials(): void
    {
        $this->expectException(AuthenticationFailedException::class);
        static::$service->login(static::$adminUserName, "hamAndEggs");
    }
}
