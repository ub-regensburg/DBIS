<?php

/**
 * @OA\Info(title="DBIS API", version="1.0", description="This document describes the API of DBIS, the Database Information System. It can be used to access all information from the DBIS system in a machine-readable way.
 * The Endpoints can all be found behind the domain **https://dbis-api.ur.de** (and for legacy reasons also **https://dbis.ur.de**).
 * The API endpoints are split into 2 groups:
     * All endpoints that return their data as XML were created to provide as much backward compatibility as possible with the old DBIS system that existed before 2025.
     * All endpoints that return their data as JSON were newly created after/during the code rewrite and are now the recommended way of querying the DBIS data.
 * We intend to disable the XML endpoints in the long run. Please migrate your tools accordingly.",
 * @OA\Contact(name="DBIS Technical Support Team", email="Technik.Dbis@bibliothek.uni-regensburg.de")
 * )
 */

use App\Action\Api\v1\Legacy\DbListeAction;
use App\Action\Api\v1\Legacy\DbListeAction2;
use App\Action\Api\v1\Legacy\FachlisteAction;
use App\Action\Api\v1\Legacy\SucheAction;
use App\Action\Api\v1\Legacy\DetailAction;
use App\Action\Api\v1\Organizations\GetOrganizationsAction;
use App\Action\Api\v1\Resources\GetAccessFormsAction;
use App\Action\Api\v1\Resources\GetAccessTypesAction;
use App\Action\Api\v1\Resources\GetLicenseFormsAction;
use App\Action\Api\v1\Resources\GetLicenseTypesAction;
use App\Action\Api\v1\Resources\GetResourceAction;
use App\Action\Api\v1\Resources\GetRelationshipsAction;
use App\Action\Api\v1\Resources\GetResourcesAction;
use App\Action\Api\v1\Resources\GetResourcesBySubjectAction;
use App\Action\Api\v1\Resources\GetResourcesGlobalAction;
use App\Action\Api\v1\Resources\GetSubjectsAction;
use App\Action\Frontend\Admin\AdminManageKeywordsPage;
use App\Action\Frontend\Admin\AdminManageResourceDraftsPage;
use App\Action\Frontend\Admin\AdminManageSubjectsPage;
use App\Action\Frontend\Admin\AdminRedirectToEditPage;
use App\Action\Frontend\Admin\AdminProfilePage;
use App\Action\Frontend\Admin\AdminManageCleanupPage;
use App\Action\Frontend\Admin\AdminContactPage;
use App\Action\Frontend\Admin\AdminFirstStepsPage;
use App\Action\Frontend\Admin\SuperadminSettingsPage;
use App\Action\Frontend\Admin\SuperadminFreeResourcesPage;
use App\Action\Frontend\Users\UsersCollectionsListPage;
use App\Action\Frontend\Users\UsersResourcesForCollectionPage;
use Slim\App;
use OpenApi\Annotations as OA;
/* Pages */
use App\Action\Frontend\Admin\AdminStartPage;
use App\Action\Frontend\Users\UsersSearchPage;
use App\Action\Frontend\Users\UsersStartPage;
use App\Action\Frontend\Users\Users404Page;
use App\Action\Frontend\Users\UsersServicesPage;
use App\Action\Frontend\Admin\Organizations\SuperadminOrganizationsEditDbisView;
use App\Action\Frontend\Admin\Organizations\SuperadminOrganizationsManagePage;
use App\Action\Frontend\Admin\Organizations\SuperadminOrganizationEditPage;
use App\Action\Frontend\Admin\AdminResourceEditPage;
use App\Action\Frontend\Admin\AdminManageRelationshipsPage;
use App\Action\Frontend\Admin\AdminResultsPage;
use App\Action\Frontend\Admin\AdminEditLicenseForResourcePage;
use App\Action\Frontend\Admin\AdminManageLicensesForResourcePage;
use App\Action\Frontend\Admin\AdminSearchPage;
use App\Action\Frontend\Users\UsersSubjectsListPage;
use App\Action\Frontend\Users\UsersResourcesForSubjectPage;
use App\Action\Frontend\Admin\AdminManageTopResourcesPage;
use App\Action\Frontend\Admin\AdminCollectionEditPage;
use App\Action\Frontend\Admin\AdminCollectionManagePage;
use App\Action\Frontend\Admin\AdminDailyStatisticsPage;
use App\Action\Frontend\Admin\AdminManageLabelsPage;
use App\Action\Frontend\Admin\AdminSelectableStatisticsPage;
use App\Action\Frontend\Admin\SuperadminPrivilegeFindUserPage;
use App\Action\Frontend\Admin\SuperadminPrivilegeUserDetailPage;
use App\Action\Frontend\Admin\Admin404Page;
/* Actions */

use App\Action\Api\v1\Auth\LogoutAction;
use App\Action\Api\v1\Auth\SetLanguageAction;
use App\Action\Api\v1\Organizations\CreateOrganizationAction;
use App\Action\Api\v1\Organizations\UpdateOrganizationAction;
use App\Action\Api\v1\Organizations\DeleteOrganizationAction;
use App\Action\Api\v1\Resources\AddLicenseToResourceAction;
use App\Action\Api\v1\Resources\UpdateLicenseInResourceAction;
use App\Action\Api\v1\Resources\RemoveLicenseFromResource;
use App\Action\Api\v1\Organizations\CreateDbisViewForOrganizationAction;
use App\Action\Api\v1\Organizations\DeleteDbisViewFromOrganizationAction;
use App\Action\Frontend\Admin\AdminLoginPage;
use App\Action\Api\v1\Auth\DummyAuthAction;
use App\Action\Frontend\Shared\FetchIcon;
use App\Action\Api\v1\Users\SetUserLanguageAction;
use App\Action\Api\v1\Users\SetUserOrganizationAction;
use App\Action\Api\v1\Organizations\SetManagedOrganization;
use App\Action\Api\v1\Resources\GetKeywordsAction;
use App\Action\Api\v1\Resources\GetHostsAction;
use App\Action\Api\v1\Resources\GetAuthorsAction;
use App\Action\Api\v1\Resources\GetPublishersAction;
use App\Action\Api\v1\Resources\GetCountriesAction;
use App\Action\Api\v1\Resources\GetLabelsAction;
use App\Action\Api\v1\Resources\UpdateKeywordAction;
use App\Action\Api\v1\Resources\UpdateLicenseLocalisationAction;
use App\Action\Frontend\Users\UsersDetailPage;
use App\Action\Frontend\Users\UsersResultsPage;
use App\Action\Frontend\Users\UsersWarpTo;

return function (App $app) {
    $app->get('/', UsersStartPage::class);
    $app->get('/resources/icons/{icon}', FetchIcon::class);

    $app->get('/admin', AdminStartPage::class);
    $app->get('/admin/', AdminStartPage::class);

    $app->post('/admin/user/language', SetLanguageAction::class);
    $app->post('/admin/user/language/', SetLanguageAction::class);
    $app->get('/admin/user/manage/{ubrId}/', SetManagedOrganization::class);

    $app->get('/admin/login', AdminLoginPage::class);
    $app->post('/admin/login', AdminLoginPage::class);
    $app->get('/admin/daten.php', function ($request, $response, $name) {
        return $response->withHeader('Location', '/admin/login')->withStatus(301);
    });
    $app->get('/superadmin/privileges/users/', SuperadminPrivilegeFindUserPage::class);
    $app->get('/superadmin/privileges/users/{userId}/', SuperadminPrivilegeUserDetailPage::class);
    $app->post('/superadmin/privileges/users/{userId}/', SuperadminPrivilegeUserDetailPage::class);
    $app->delete(
        '/superadmin/privileges/users/{userId}/privileges/{privilegeId}/',
        SuperadminPrivilegeUserDetailPage::class
    );
    $app->get('/admin/manage/{ubrId}', AdminStartPage::class);
    $app->get('/admin/manage/{ubrId}/', AdminStartPage::class);
    $app->get('/superadmin/organizations/', SuperadminOrganizationsManagePage::class);

    $app->get('/admin/redirect_to_edit', AdminRedirectToEditPage::class);

    $app->get('/superadmin/settings/', SuperadminSettingsPage::class);
    $app->post('/superadmin/settings/', SuperadminSettingsPage::class);
    $app->put('/superadmin/settings/', SuperadminSettingsPage::class);

    $app->get('/superadmin/freeresources/', SuperadminFreeResourcesPage::class);
    $app->post('/superadmin/freeresources/', SuperadminFreeResourcesPage::class);
    $app->put('/superadmin/freeresources/', SuperadminFreeResourcesPage::class);

    $app->get('/admin/manage/{ubrId}/subjects/', AdminManageSubjectsPage::class);
    $app->post('/admin/manage/{ubrId}/subjects/', AdminManageSubjectsPage::class);

    $app->get('/admin/manage/{ubrId}/subjects/top-resources/', AdminManageTopResourcesPage::class);
    $app->get('/admin/manage/{ubrId}/subjects/{subjectId}/top-resources/', AdminManageTopResourcesPage::class);
    $app->get('/admin/manage/{ubrId}/collections/{collectionId}/top-resources/', AdminManageTopResourcesPage::class);
    $app->put('/admin/manage/{ubrId}/subjects/{subjectId}/top-resources/', AdminManageTopResourcesPage::class);
    $app->put('/admin/manage/{ubrId}/collections/{collectionId}/top-resources/', AdminManageTopResourcesPage::class);

    $app->get('/admin/manage/{ubrId}/organization/', SuperadminOrganizationEditPage::class);
    $app->put('/admin/manage/{ubrId}/organization/', SuperadminOrganizationEditPage::class);
    $app->get('/superadmin/organizations/new/', SuperadminOrganizationEditPage ::class);
    $app->post('/superadmin/organizations/new/', SuperadminOrganizationEditPage ::class);
    $app->get('/superadmin/organizations/{ubrId}/', SuperadminOrganizationEditPage::class);
    $app->put('/superadmin/organizations/{ubrId}/', SuperadminOrganizationEditPage::class);
    $app->delete('/superadmin/organizations/{ubrId}/', SuperadminOrganizationEditPage::class);
    $app->delete('/admin/manage/{ubrId}/organization/', SuperadminOrganizationEditPage::class);
    $app->get('/superadmin/organizations/{ubrId}/views/', SuperadminOrganizationsEditDbisView::class);
    $app->post('/superadmin/organizations/{ubrId}/views/', SuperadminOrganizationsEditDbisView::class);
    $app->delete('/superadmin/organizations/{ubrId}/views/', SuperadminOrganizationsEditDbisView::class);

    $app->get('/admin/resources/', AdminSearchPage::class);
    $app->get('/admin/manage/{ubrId}/resources/', AdminSearchPage::class);
    $app->get('/admin/resources/results/', AdminResultsPage::class);
    $app->get('/admin/manage/{ubrId}/resources/results/', AdminResultsPage::class);
    // TODO: rename this to in #11 to AdminResourceEditPage
    $app->get('/admin/manage/{ubrId}/resources/new/', AdminResourceEditPage::class);
    $app->post('/admin/manage/{ubrId}/resources/new/', AdminResourceEditPage::class);
    $app->post('/admin/manage/{ubrId}/resources/{id}/', AdminResourceEditPage::class);
    $app->put('/admin/manage/{ubrId}/resources/{id}/', AdminResourceEditPage::class);
    $app->get('/admin/manage/{ubrId}/resources/{id}/', AdminResourceEditPage::class)
        ->setName('manageResourceWithinOrganization');
    $app->get('/admin/resources/new/', AdminResourceEditPage::class);
    $app->post('/admin/resources/new/', AdminResourceEditPage::class);
    $app->get('/admin/resources/{id}/', AdminResourceEditPage::class)->setName('manageResource');
    $app->put('/admin/resources/{id}/', AdminResourceEditPage::class);
    $app->delete('/admin/manage/{ubrId}/resources/{id}/', AdminResourceEditPage::class);

    $app->get('/admin/manage/{ubrId}/relationships/', AdminManageRelationshipsPage::class);
    $app->get('/admin/relationships/', AdminManageRelationshipsPage::class);
    $app->post('/admin/manage/{ubrId}/relationships/', AdminManageRelationshipsPage::class);
    $app->post('/admin/relationships/', AdminManageRelationshipsPage::class);

    $app->get('/admin/manage/{ubrId}/drafts/', AdminManageResourceDraftsPage::class);

    $app->get('/admin/manage/{ubrId}/collections/', AdminCollectionManagePage::class);
    $app->get('/admin/collections/', AdminCollectionManagePage::class);
    $app->get('/admin/collections/new/', AdminCollectionEditPage::class);
    $app->post('/admin/collections/new/', AdminCollectionEditPage::class);
    $app->get('/admin/manage/{ubrId}/collections/new/', AdminCollectionEditPage::class);
    $app->post('/admin/manage/{ubrId}/collections/new/', AdminCollectionEditPage::class);
    $app->get('/admin/manage/{ubrId}/collections/{id}/', AdminCollectionEditPage::class);
    $app->put('/admin/manage/{ubrId}/collections/{id}/', AdminCollectionEditPage::class);
    // TODO: $app->delete('/admin/manage/{ubrId}/collections/{id}/', AdminCollectionEditPage::class);

    $app->get('/admin/manage/{ubrId}/keywords/', AdminManageKeywordsPage::class);
    $app->get('/admin/keywords/', AdminManageKeywordsPage::class);

    $app->get('/admin/manage/{ubrId}/cleanup/', AdminManageCleanupPage::class);
    $app->post('/admin/manage/{ubrId}/cleanup/', AdminManageCleanupPage::class);   

    $app->get('/admin/manage/{ubrId}/firststeps/', AdminFirstStepsPage::class);
    $app->get('/admin/firststeps/', AdminFirstStepsPage::class);

    $app->get('/admin/manage/{ubrId}/contact/', AdminContactPage::class);
    $app->get('/admin/contact/', AdminContactPage::class);

    $app->get('/admin/manage/{ubrId}/profile/', AdminProfilePage::class);
    $app->get('/admin/profile/', AdminProfilePage::class);

    // legacy route
    $app->get('/admin/databases/{resourceId}/licenses/', AdminManageLicensesForResourcePage::class);
    $app->get('/admin/resources/{resourceId}/licenses/', AdminManageLicensesForResourcePage::class)->setName('manageLicenses');
    // legacy route
    $app->get('/admin/databases/{resourceId}/licenses/new/', AdminEditLicenseForResourcePage::class);
    $app->get('/admin/resources/{resourceId}/licenses/new/', AdminEditLicenseForResourcePage::class);
    $app->post('/admin/resources/{resourceId}/licenses/new/', AdminEditLicenseForResourcePage::class);
    // legacy route
    $app->get('/admin/databases/{resourceId}/licenses/{licenseId}/', AdminEditLicenseForResourcePage::class);
    $app->get('/admin/resources/{resourceId}/licenses/{licenseId}/', AdminEditLicenseForResourcePage::class);
    $app->put('/admin/resources/{resourceId}/licenses/{licenseId}/', AdminEditLicenseForResourcePage::class);
    $app->delete('/admin/resources/{resourceId}/licenses/{licenseId}/', AdminEditLicenseForResourcePage::class);
    // legacy route
    $app->get(
        '/admin/manage/{organizationId}/databases/{resourceId}/licenses/',
        AdminManageLicensesForResourcePage::class
    );
    $app->get(
        '/admin/manage/{organizationId}/resources/{resourceId}/licenses/',
        AdminManageLicensesForResourcePage::class
    )->setName('manageLicensesWithinOrganization');
    $app->post(
        '/admin/manage/{organizationId}/resources/{resourceId}/licenses/',
        AdminManageLicensesForResourcePage::class
    );
    // legacy route
    $app->get(
        '/admin/manage/{organizationId}/databases/{resourceId}/licenses/new/',
        AdminEditLicenseForResourcePage::class
    );
    // ------------
    $app->post(
        '/admin/manage/{organizationId}/resources/{resourceId}/licenses/new/',
        AdminEditLicenseForResourcePage::class
    );
    $app->get(
        '/admin/manage/{organizationId}/resources/{resourceId}/licenses/new/',
        AdminEditLicenseForResourcePage::class
    );
    // legacy route
    $app->get(
        '/admin/manage/{organizationId}/databases/{resourceId}/licenses/{licenseId}/',
        AdminEditLicenseForResourcePage::class
    );
    $app->get(
        '/admin/manage/{organizationId}/resources/{resourceId}/licenses/{licenseId}/',
        AdminEditLicenseForResourcePage::class
    );
    $app->put(
        '/admin/manage/{organizationId}/resources/{resourceId}/licenses/{licenseId}/',
        AdminEditLicenseForResourcePage::class
    );
    $app->delete(
        '/admin/manage/{organizationId}/resources/{resourceId}/licenses/{licenseId}/',
        AdminEditLicenseForResourcePage::class
    );

    $app->get(
        '/admin/manage/{organizationId}/statistics/selectable/',
        AdminSelectableStatisticsPage::class
    );

    $app->get(
        '/admin/manage/{organizationId}/statistics/daily/',
        AdminDailyStatisticsPage::class
    );

    $app->get(
        '/admin/manage/{organizationId}/labels/',
        AdminManageLabelsPage::class
    );
    $app->post(
        '/admin/manage/{organizationId}/labels/',
        AdminManageLabelsPage::class
    );

    $app->get('/warpto', UsersWarpTo::class);
    $app->get('/dbinfo/warpto.php', function ($request, $response, $name) {
        return $response->withHeader('Location', '/warpto?'.$request->getUri()->getQuery())->withStatus(301);
    });

    /**
     * @OA\Get(
     *     path="/api-description",
     *     tags={"documentation"},
     *     summary="OpenAPI JSON File that describes the API",
     *     @OA\Response(response="200", description="OpenAPI Description File"),
     * )
     */
    $app->get('/api-description', function ($request, $response, $args) {
        require __DIR__ . '/../vendor/autoload.php';

        $openapi = \OpenApi\scan(__DIR__, ['exclude' => ['tests'],
            'pattern' => '*.php']);
        $response->getBody()->write(json_encode($openapi));
        return $response->withHeader('Content-Type', 'application/json');
    });


    /**
     * @OA\Parameter(
     *     parameter="url_bibid",
     *     name="url_bibid",
     *     in="query",
     *     example="UBR",
     *     required=false,
     *     description="The unique identifier of a participating organization, a.k.a. URL-BIBID. It is shown in the admin area.<br>
           In many (but not all) cases the old DBIS_ID/BIB_ID also works.<br>
           If this parameter is supplied, the returned data represents the local view of that organization instead of the global data.<br>
           This parameter is ignored in all JSON endpoints - at the moment only global data can be queried due to licensing reasons.",
     *     @OA\Schema(type="string")
     * ),
     * @OA\Parameter(
     *     parameter="language",
     *     name="language",
     *     in="query",
     *     example="de",
     *     required=false,
     *     description="Select the language of the returned data.<br>
           Options are either **de** or **en**.",
     *     @OA\Schema(type="string", default="de")
     * ),
     * @OA\Parameter(
     *     parameter="lang",
     *     name="lang",
     *     in="query",
     *     example="de",
     *     required=false,
     *     description="Select the language of the returned data.<br>
           Options are either **de** or **en**.<br>
           Note that for all XML endpoints, the structure of the returned data was initially not designed with multi-language support in mind, so this parameter only works in a best-effort manner there.",
     *     @OA\Schema(type="string", default="de")
     * ),
     * @OA\Parameter(
     *     parameter="xmloutput",
     *     name="xmloutput",
     *     in="query",
     *     example="true",
     *     required=true,
     *     description="Must be set to 1 / true.",
     *     @OA\Schema(type="boolean", default="true")
     * ),
     */

    $app->get('/api', \App\Action\Api\v1\ApiAction::class);
    $app->get('/api/v1/hosts', GetHostsAction::class);
    $app->get('/api/v1/relations/{resourceId}', GetRelationshipsAction::class);
    $app->post('/api/v1/databases/{resourceId}/licenses/new', AddLicenseToResourceAction::class);
    $app->post(
        '/api/v1/manage/{organizationId}/databases/{resourceId}/licenses/new',
        AddLicenseToResourceAction::class
    );
    $app->put(
        '/api/v1/databases/{resourceId}/licenses/{licenseId}',
        UpdateLicenseInResourceAction::class
    );
    $app->put(
        '/api/v1/manage/{organizationId}/databases/{resourceId}/licenses/{licenseId}',
        UpdateLicenseInResourceAction::class
    );
    $app->delete('/api/v1/databases/{resourceId}/licenses/{licenseId}', RemoveLicenseFromResource::class);
    $app->delete(
        '/api/v1/manage/{organizationId}/databases/{resourceId}/licenses/{licenseId}',
        RemoveLicenseFromResource::class
    );

    $app->get('/api/v1/resources', GetResourcesAction::class);

    $app->put('/api/v1/keyword/{keywordId}', UpdateKeywordAction::class);

    /**
     * @OA\Get(
     *  path="/api/v1/subjects",
     *  summary="Lists the details of all defined subjects.",
     *  tags={"JSON"},
     *  operationId="getSubjects",
     *  @OA\Parameter(ref="#/components/parameters/language"),
     *  @OA\Response(response="200", description="Returns a list with details on all defined subjects.", @OA\MediaType(mediaType="application/json"))
     * )
     */
    $app->get('/api/v1/subjects', GetSubjectsAction::class);

    /**
     * @OA\Get(
     *  path="/api/v1/resourceIdsBySubject/{subject_id}",
     *  summary="Lists all resources associated with a given subject.",
     *  tags={"JSON"},
     *  @OA\Parameter(name="subject_id", in="path", required=true, example="42", description="The internal ID of the subject (can be obtained from the endpoint **subjects**).", @OA\Schema(type="integer")),
     *  @OA\Response(response="200", description="Returns a list with the IDs of all resources associated with the given subject.", @OA\MediaType(mediaType="application/json")),
     *  @OA\Response(response="404", description="Is returned when the subject was not found.")
     * )
     */
    $app->get('/api/v1/resourceIdsBySubject/{subjectId}', GetResourcesBySubjectAction::class);

    /**
     * @OA\Get(
     *  path="/api/v1/resourceIdsGlobal",
     *  summary="Lists all public/global resources.",
     *  tags={"JSON"},
     *  @OA\Response(response="200", description="Returns a list with the IDs of all existing resources.", @OA\MediaType(mediaType="application/json")),
     * )
     */
    $app->get('/api/v1/resourceIdsGlobal', GetResourcesGlobalAction::class);

    /**
     * @OA\Get(
     *  path="/api/v1/resource/{dbis_res_id}",
     *  summary="Get all details for one resource.",
     *  tags={"JSON"},
     *  @OA\Parameter(name="dbis_res_id", in="path", required=true, example="1168", description="ID of the resource of interest. Use other endpoints (like **resourceIdsBySubject** or **resourceIdsGlobal**) to get lists of these ids."),
     *  @OA\Parameter(ref="#/components/parameters/language"),
     *  @OA\Response(response="200", description="Returns all available details for the specified resource.", @OA\MediaType(mediaType="application/json")),
     *  @OA\Response(response="404", description="Returned when a resource with the given id could not be found.")
     * )
     */
    $app->get('/api/v1/resource/{resourceId}', GetResourceAction::class);

    /**
     * @OA\Get(
     *  path="/api/v1/licenseTypes",
     *  summary="Lists details of all defined license types.",
     *  tags={"JSON"},
     *  @OA\Response(response="200", description="Returns a list with details on all defined license types.", @OA\MediaType(mediaType="application/json"))
     * )
     */
    $app->get('/api/v1/licenseTypes', GetLicenseTypesAction::class);

    /**
     * @OA\Get(
     *  path="/api/v1/licenseForms",
     *  summary="Lists details of all defined license forms.",
     *  tags={"JSON"},
     *  @OA\Response(response="200", description="Returns a list with details on all defined license forms.", @OA\MediaType(mediaType="application/json"))
     * )
     */
    $app->get('/api/v1/licenseForms', GetLicenseFormsAction::class);

    /**
     * @OA\Get(
     *  path="/api/v1/accessTypes",
     *  summary="Lists details of all defined access types.",
     *  tags={"JSON"},
     *  @OA\Response(response="200", description="Returns a list with details on all defined access types.", @OA\MediaType(mediaType="application/json"))
     * )
     */
    $app->get('/api/v1/accessTypes', GetAccessTypesAction::class);

    /**
     * @OA\Get(
     *  path="/api/v1/accessForms",
     *  summary="Lists details of all defined access forms.",
     *  tags={"JSON"},
     *  @OA\Response(response="200", description="Returns a list with details on all defined access forms.", @OA\MediaType(mediaType="application/json"))
     * )
     */
    $app->get('/api/v1/accessForms', GetAccessFormsAction::class);

    $app->get('/api/v1/search', GetResourcesAction::class);

    /**
     * This docblock is hidden in Swagger. Re-add all the (at)OA prefixes to show it.
     * Get(
     *  path="/api/v1/organizations",
     *  summary="Get data on all registered organizations.",
     *  tags={"JSON"},
     *  operationId="getOrganizations",
     *  Parameter(ref="#/components/parameters/language"),
     *  Response(response="200", description="Get the details of all registered organizations.", MediaType(mediaType="application/json"))
     * )
     */
    //$app->get('/api/v1/organizations', GetOrganizationsAction::class);

    $app->get('/api/v1/auth/logout', LogoutAction::class);
    $app->post('/api/v1/organizations/', CreateOrganizationAction::class);
    $app->delete('/api/v1/organizations/{ubrId}', DeleteOrganizationAction::class);
    $app->put('/api/v1/organizations/{ubrId}', UpdateOrganizationAction::class);

    $app->delete('/api/v1/organizations/{ubrId}/views', DeleteDbisViewFromOrganizationAction::class);
    $app->post('/api/v1/organizations/{ubrId}/views', CreateDbisViewForOrganizationAction::class);

    $app->get('/api/v1/keywords', GetKeywordsAction::class);

    $app->get('/api/v1/authors', GetAuthorsAction::class);

    $app->get('/api/v1/publishers', GetPublishersAction::class);

    $app->get('/api/v1/countries', GetCountriesAction::class);

    $app->get('/api/v1/labels', GetLabelsAction::class);

    $app->post('/api/v1/license-localisation', UpdateLicenseLocalisationAction::class);

    $app->get('/services', UsersServicesPage::class);

    // SEARCH
    $app->get('/search', UsersSearchPage::class);
    $app->get('/{organizationId}/search', UsersSearchPage::class);

    // RESULTS
    $app->get('/results', UsersResultsPage::class);
    $app->get('/{organizationId}/results', UsersResultsPage::class);

    // DETAILS
    $app->get('/{organizationId}/resources/{resourceId}', UsersDetailPage::class);
    $app->get('/resources/{resourceId}', UsersDetailPage::class);

    // BROWSE - SUBJECTS
    $app->get('/browse/subjects/', UsersSubjectsListPage::class);
    $app->get('/{organizationId}/browse/subjects/', UsersSubjectsListPage::class);

    // BROWSE- SUBJECTS - SHOW
    $app->get('/browse/subjects/{subjectId}/', UsersResourcesForSubjectPage::class);
    $app->get('/{organizationId}/browse/subjects/{subjectId}/', UsersResourcesForSubjectPage::class)
        ->setName('subject');

    // BROWSE - COLLECTIONS - SHOW
    $app->get(
        '/{organizationId}/browse/collections/',
        UsersCollectionsListPage::class
    )->setName('collectionsWithinOrganization');
    $app->get('/{organizationId}/browse/collections/{collectionId}/', UsersResourcesForCollectionPage::class)
        ->setName('collectionWithinOrganization');

    // LEGACY ROUTE
    /**
     * @OA\Get(
     *  path="/detail.php",
     *  summary="Get all details for one resource. Either global or for an organization.",
     *  tags={"XML"},
     *  @OA\Parameter(ref="#/components/parameters/lang"),
     *  @OA\Parameter(ref="#/components/parameters/url_bibid"),
     *  @OA\Parameter(name="titel_id", in="query", example="6908", required=true, description="Resource ID of the dataset that should be returned.", @OA\Schema(type="integer")),
     *  @OA\Parameter(ref="#/components/parameters/xmloutput"),
     *  @OA\Response(response="200", description="Returns all available details for the specified resource.", @OA\MediaType(mediaType="application/xml")),
     *  @OA\Response(response="404", description="Is returned when either the resurce or the institution was not found.")
     * )
     */
    $app->get('/detail.php', DetailAction::class);
    $app->get('/dbinfo/detail.php', DetailAction::class);
    /**
     * Get a list of all subjects. If an organization is specified, locally-defined subjects and collections (if marked as subject) are also contained.
     * @OA\Get(
     *  path="/fachliste.php",
     *  tags={"XML"},
     *  @OA\Parameter(ref="#/components/parameters/lang"),
     *  @OA\Parameter(ref="#/components/parameters/url_bibid"),
     *  @OA\Parameter(ref="#/components/parameters/xmloutput"),
     *  @OA\Response(response="200", description="Returns the list of all subjects.", @OA\MediaType(mediaType="application/xml")
     *  )
     * )
     */
    $app->get('/fachliste.php', FachlisteAction::class);
    $app->get('/dbinfo/fachliste.php', FachlisteAction::class);

    /**
     * @OA\Get(
     *  path="/dbliste.php",
     *  summary="This is a search function that returns a list of resource entries. Multiple combinations of parameters are possible.",
     *  description="This endpoint unites multiple modes of search (due to historical reasons):<br>
     - List all resources, either globally or from an organization's point of view. This search mode is triggered when none of the other modes are not used. Using pagination is advisable in this mode.<br>
     - List the resources of one subject or collection: Use _lett=f_ and _gebiete_ or _lett=c_ and _collid_).<br>
     - Find resources by their type: Use _db_type_ with an appropriate type_id (or supply _sort=type_ in combination with one of the previous modes for a somewhat similar result).<br>
     - Search by free text (simple): Use _Suchwort_ to supply your query text.<br>
     - Search by free text (complex): The parameters _jq_termN_, _jq_typeN_, etc. allow for more complex search queries. This functionality was only partially recreated in the new system.<br>",
     *  @OA\Parameter(name="Suchwort", in="query", example="beck online", required=false, description="Used for textual search.", @OA\Schema(type="string", default="")),
     *  @OA\Parameter(name="gebiete", in="query", example="15", required=false, description="When querying the resources of a subject, put the subject_id or collection_id here.", @OA\Schema(type="string", default="")),
     *  @OA\Parameter(name="lett", in="query", example="f, c, a", required=false, description="Selects whether a subject (f) or a collection (c) is wanted. Alternatively, requests alphabetical sorting (a).", @OA\Schema(type="string", default="")),
     *  @OA\Parameter(name="offset", in="query", example="", required=false, description="For pagiation. Pagination and sort=type are mutually exclusive.", @OA\Schema(type="string", default="")),
     *  @OA\Parameter(name="hits_per_page", in="query", example="", required=false, description="For pagination. Pagination and sort=type are mutually exclusive.", @OA\Schema(type="string", default="")),
     *  @OA\Parameter(name="sort", in="query", example="alph, type, (none)", required=false, description="Sort the results. This parameter is usually superfluaous, the results are sorted alphabetically anyway. Pagination and sort=type are mutually exclusive.", @OA\Schema(type="string", default="")),
     *  @OA\Parameter(name="db_type", in="query", example="", required=false, description="Filters for one or more resource types (this parameter can be added multiple times.). The search result will only contain resources with the given db_type_id(s).", @OA\Schema(type="string", default="")),
     *  @OA\Parameter(name="colors", in="query", example="", required=false, description="(translated to license-,access-,-type,-form)", @OA\Schema(type="string", default="")),
     *  tags={"XML"},
     *  @OA\Parameter(ref="#/components/parameters/lang"),
     *  @OA\Parameter(ref="#/components/parameters/url_bibid"),
     *  @OA\Parameter(ref="#/components/parameters/xmloutput"),
     *  @OA\Response(response="200", description="Returns a list of resources according to the query parameters.", @OA\MediaType(mediaType="application/xml")),
     *  @OA\Response(response="404", description="Is returned when either the resurce or the institution was not found.")
     * )
     */
    $app->get('/dbliste.php', DbListeAction::class);
    $app->get('/dbinfo/dbliste.php', DbListeAction::class);

    /**
     * @OA\Get(
     *  path="/suche.php",
     *  summary="Returns various static information that may be useful for other endpoints.",
     *  tags={"XML"},
     *  @OA\Parameter(ref="#/components/parameters/lang"),
     *  @OA\Parameter(ref="#/components/parameters/xmloutput"),
     *  @OA\Response(response="200", description="Returns various static information.", @OA\MediaType(mediaType="application/xml")),
     * )
     */
    $app->get('/suche.php', SucheAction::class);
    $app->get('/dbinfo/suche.php', SucheAction::class);

    // SEEMS TO BE UNUSED -> not implemented
    //$app->get('/index.php', UsersDetailPage::class);

    // LEGACY ROUTE: redirect /frontdoor.php?titel_id={resource_id} to /???/resources/{resource_id}
    $handler_frontdoor = function ($request, $response, $name) {
        if (isset($request->getQueryParams()['titel_id'])) { // maybe this constraint is not necessary
            return $response->withHeader('Location', '/detail.php?'.$request->getUri()->getQuery())->withStatus(301);
        } else {
            $response->getBody()->write('Missing query parameter "titel_id"');
            return $response->withStatus(400);
        }
    };
    $app->get('/frontdoor.php', $handler_frontdoor);
    $app->get('/dbinfo/frontdoor.php', $handler_frontdoor);

    // This redirect was added in Oct 2024 for UB Emedien <emedien@ub.uni-tuebingen.de>:
    // from: http://dbis.uni-regensburg.de/dbinfo/frontdoor.phtml?titel_id=103013&bib_id=ubtue
    // to: https://dbis.uni-regensburg.de/frontdoor.php?titel_id=103013&bib_id=ubtue
    $app->get('/dbinfo/frontdoor.phtml', function ($request, $response, $name) {
        return $response->withHeader('Location', '/frontdoor.php?'.$request->getUri()->getQuery())->withStatus(301);
    });

    // SET LANGUAGE/ORG
    $app->post('/user/language', SetUserLanguageAction::class);
    $app->post('/user/organization', SetUserOrganizationAction::class);

    // START (with ID - do not move up, since /search /admin etc.
    // have to be defined with higher hierarchy than the orgId)
    $app->get('/{organizationId}', UsersStartPage::class);
    $app->get('/{organizationId}/', UsersStartPage::class);

    /* Dummy routes */
    $app->get('/api/v1/auth/dummylogin/{type}', DummyAuthAction::class);

    $app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response, $args) use ($app) {
        $path = $request->getUri()->getPath();
    
        if (strpos($path, 'admin') !== false) {
            // Retrieve the Admin404Handler from the container
            $handler = $app->getContainer()->get(Admin404Page::class);
        } else {
            // Retrieve the User404Handler from the container
            $handler = $app->getContainer()->get(Users404Page::class);
        }

        return $handler($request, $response, $args);
    });
};
