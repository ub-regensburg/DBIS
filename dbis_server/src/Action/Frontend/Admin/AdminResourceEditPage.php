<?php

declare(strict_types=1);

namespace App\Action\Frontend\Admin;

use App\Domain\Organizations\OrganizationService;
use App\Domain\Resources\Entities\ExternalID;
use App\Domain\Resources\Entities\Url;
use App\Domain\Resources\ResourceService;
use App\Domain\Shared\AuthService;
use App\Infrastructure\Shared\CountryProvider;
use App\Infrastructure\Shared\ResourceProvider;
use App\Domain\Resources\Entities\Author;
use App\Domain\Resources\Entities\Keyword;
use App\Domain\Resources\Entities\Resource;
use App\Domain\Resources\Entities\Subject;
use App\Domain\Resources\Entities\Type;
use App\Domain\Resources\Entities\AlternativeTitle;
use App\Domain\Resources\Entities\UpdateFrequency;
use App\Domain\Resources\Entities\Country;
use App\Infrastructure\Resources\ResourceRepository;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;
use DateTime;

/**
 * AdminCreateOrganisationPage
 *
 * Form for creating a new organization
 */
class AdminResourceEditPage extends AdminBasePage
{
    protected ResourceService $resourceService;

    // TODO: OrganizationService should not be in the base class
    public function __construct(
        ResourceProvider $rp,
        AuthService $auth,
        OrganizationService $service,
        CountryProvider $countryProvider,
        ResourceService $resourceService
    ) {
        parent::__construct($rp, $auth, $service, $countryProvider, $resourceService);

        $this->resourceService = $resourceService;
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // Set organisation according to route parameter
        if ($request->getAttribute('ubrId')) {
            parent::setAdministratedOrganization($request->getAttribute('ubrId'));
        }
        // simple auth guard
        if (!$this->authService->isSessionAuthenticated()) {
            return $response->withHeader('Location', '/admin/login')->withStatus(302);
        } elseif (!$this->isSuperAdmin && !$this->isAdmin && !$this->isSubjectSpecialist) {
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }

        if ($request->getMethod() == "GET") {
            return $this->handleGetRequest($request, $response);
        } elseif ($request->getMethod() == "PUT") {
            if (isset($request->getParsedBody()['delete'])) {
                return $this->handleDeleteRequest($request, $response);
            } else {
                return $this->handleUpdateRequest($request, $response);
            }
            
        } elseif ($request->getMethod() == "POST") {
            return $this->handleCreateRequest($request, $response);
        } elseif ($request->getMethod() == "DELETE") {
            return $this->handleDeleteRequest($request, $response);
        } else {
            return $this->handleGetRequest($request, $response);
        }
    }

    private function handleGetRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $id = $request->getAttribute('id') ? (int)$request->getAttribute('id') : null;
        $localOrganizationId = $this->administeredOrganization ? $this->administeredOrganization->getUbrId() : null;

        $resourceGlobal = null;
        $resourceLocal = null;
        if ($id) {
            $resourceGlobal =
                $this->resourceService->getResourceById_NEW($id, null, ResourceRepository::GLOBAL);
            if ($localOrganizationId) {
                $resourceLocal =
                    $this->resourceService->getResourceById_NEW($id, $localOrganizationId, ResourceRepository::LOCAL);
            }
        }

        $errors = array();
        $localizations = $this->getLocalizationSettingsFromresource($resourceLocal);

        return $this->renderPage($request, $response, $resourceGlobal, $resourceLocal, $errors, $localizations);
    }

    private function handleUpdateRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        if ($this->isAdmin || $this->isSuperAdmin) {
            $params = $request->getQueryParams();

            $id = (int)$request->getAttribute('id');
            $localOrganizationId = $this->administeredOrganization ? $this->administeredOrganization->getUbrId() : null;

            $previousResourceGlobal = $this->resourceService->getResourceById_NEW(
                $id,
                null,
                $this->resourceService->globalResource()
            );
            $resourceGlobal = $this->parseBodyGlobal($request->getParsedBody());
            $resourceGlobal->setId($id);
            $resourceGlobal->setLicenses($previousResourceGlobal->getLicenses());
            $errorsGlobal = $resourceGlobal->validate();

            $previousResourceLocal = $this->resourceService->getResourceById_NEW(
                $id,
                $localOrganizationId,
                $this->resourceService->localResource()
            );

            $localizations = $this->parseLocalizationSettings($request->getParsedBody());

            $resourceLocal = $this->parseBodyLocal($request->getParsedBody(), $localizations);
            $resourceLocal->setId($id);
            $resourceLocal->setLicenses($previousResourceLocal->getLicenses());

            // $errorsLocal = $resourceLocal->validate();
            // $errors = array_merge($errorsGlobal, $errorsLocal);           

            $errors = $errorsGlobal;
            
            if (count($errors) == 0) {
                $this->resourceService->updateResource($resourceGlobal, $localOrganizationId, $resourceLocal, $previousResourceGlobal);
                $url = explode('?', $_SERVER['REQUEST_URI'])[0];
                header("Location: {$url}?updated_successfully=1", true, 303);
                exit();
            } else {
                unset($params['updated_successfully']);
                unset($params['created_successfully']);
                $request = $request->withQueryParams($params);
                // TODO: Create combined resource before rendering page here - new function?
                return $this->renderPage($request, $response, $resourceGlobal, $resourceLocal, $errors, $localizations);
            }
        }
    }

    private function handleCreateRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        if ($this->isAdmin || $this->isSuperAdmin) {
            $params = $request->getQueryParams();

            $localOrganizationId = $this->administeredOrganization ? $this->administeredOrganization->getUbrId() : null;

            $localizations = $this->parseLocalizationSettings($request->getParsedBody());

            $resourceGlobal = $this->parseBodyGlobal($request->getParsedBody());

            $resourceLocal = $this->parseBodyLocal($request->getParsedBody(), $localizations);
            $errorsGlobal = $resourceGlobal->validate();

            // $errorsLocal = $resourceLocal->validate();
            // $errors = array_merge($errorsGlobal, $errorsLocal);

            $errors = $errorsGlobal;
            $this->params['errors'] = $errors;
            if (count($errors) === 0) {
                $resourceGlobal->setCreatedBy($localOrganizationId);
                
                $id = $this->resourceService->createResource($resourceGlobal, $localOrganizationId, $resourceLocal);

                $url = str_replace("/new/", "/{$id}/", $_SERVER['REQUEST_URI']);
                $url = explode('?', $url)[0];
                header("Location: {$url}?created_successfully=1", true, 303);
                exit();
            } else {
                unset($params['updated_successfully']);
                unset($params['created_successfully']);
                $request = $request->withQueryParams($params);
                return $this->renderPage($request, $response, $resourceGlobal, $resourceLocal, $errors, $localizations);
            }
        }
    }

    private function handleDeleteRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        if ($this->isAdmin || $this->isSuperAdmin) {
            $resourceId = (int) $request->getAttribute('id');

            $resource = $this->resourceService->getResourceById_NEW($resourceId, $this->organization_id);

            if ($this->organization_id == $resource->getCreatedBy() || $this->isSuperAdmin) {
                $this->resourceService->removeResource($resource, $this->organization_id, $this->isSuperAdmin);

                header("Location: /admin/manage/$this->organization_id/resources/new/?deleted_successfully=1", true, 303);
            } else {

            }
        }
    }

    private function renderPage(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ?Resource $resourceGlobal = null,
        ?Resource $resourceLocal = null,
        array $errors = [],
        array $localizations = []
    ) {
        $view = Twig::fromRequest($request);
        $language = $this->authService->getAuthenticatedUser()->getLanguage();
        $localOrganizationId = $this->administeredOrganization ? $this->administeredOrganization->getUbrId() : null;

        if (sizeof($errors) == 0) {
            if ($resourceGlobal) {
                $resourceLocal = $this->resourceService->getResourceById_NEW(
                    $resourceGlobal->getId(),
                    $localOrganizationId,
                    $this->resourceService->localResource()
                );
                $resourceGlobal = $this->resourceService->getResourceById_NEW(
                    $resourceGlobal->getId(),
                    null,
                    $this->resourceService->globalResource()
                );
            } else {
                $resourceLocal = null;
                $resourceGlobal = null;
            }
        }

        $types = array_map(function ($type) use ($language) {
            return $type->toI18nAssocArray($language);
        }, $this->resourceService->getTypes());
        /* Not needed
        $update_frequencies = array_map(function ($update_frequency) use ($language) {
            return $update_frequency->toI18nAssocArray($language);
        }, $this->resourceService->getUpdateFrequencies());
        */
        $subjects = array_map(function ($subject) use ($language) {
            return $subject->toI18nAssocArray($language);
        }, $this->resourceService->getSubjects(
            ['sort_language' => $language, 'without_resources' => true]
        ));

        $subjectsLocal = array_map(function ($subject) use ($language) {
            return $subject->toI18nAssocArray($language);
        }, $this->resourceService->getSubjects(
            ['sort_language' => $language,
                'organizationId' => $localOrganizationId, 'without_resources' => true]
        ));

        $countries = array_map(function ($country) use ($language) {
            return $country->toI18nAssocArray($language);
        }, $this->resourceService->getCountries());

        $selectedSubjectIdsGlobal = $resourceGlobal ? array_map(function ($subject) {
            return $subject->getId();
        }, $resourceGlobal->getSubjects()) : null;

        $selectedTypeIdsGlobal = $resourceGlobal ? array_map(function ($type) {
            return $type->getId();
        }, $resourceGlobal->getTypes()) : null;

        $selectedCountryIdsGlobal = $resourceGlobal ? array_map(function ($country) {
            return $country->getId();
        }, $resourceGlobal->getCountries()) : null;

        $selectedSubjectIdsLocal = $resourceLocal ? array_map(function ($subject) {
            return $subject->getId();
        }, $resourceLocal->getSubjects()) : null;

        $selectedTypeIdsLocal = $resourceLocal ? array_map(function ($type) {
            return $type->getId();
        }, $resourceLocal->getTypes()) : null;

        $selectedCountryIdsLocal = $resourceLocal ? array_map(function ($country) {
            return $country->getId();
        }, $resourceLocal->getCountries()) : null;

        $selectedExternalIds = $resourceGlobal ? array_map(function ($externalId) {
            return $externalId->toAssocArray();
        }, $resourceGlobal->getExternalIDs()) : null;

        $canBeDeleted = false;
        if($resourceGlobal and $resourceGlobal->getCreatedBy() == $localOrganizationId) {
            $createdAt = new DateTime($resourceGlobal->getCreatedAt());

            // Get the current date (without time)
            $currentDate = new DateTime();
            $currentDate->setTime(0, 0); // Set time to 00:00:00 to ignore the time part

            // Convert the 'created_at' to a date without time for comparison
            $createdDate = $createdAt->setTime(0, 0);

            if ($createdDate == $currentDate) {
                $canBeDeleted = true;
            }
        }

        if ($this->user->isSuperadmin()) {
            $canBeDeleted = true;
        }

        $this->params['can_be_deleted'] = $canBeDeleted;

        $this->params['organization'] = $localOrganizationId;
        $this->params['is_super'] = $this->user->isSuperadmin();
        $this->params['types'] = $types;
        $this->params['resourceLocalI18n'] = $resourceLocal ? $resourceLocal->toI18nAssocArray($language) : null;

        $this->params['resourceGlobalI18n'] = $resourceGlobal ? $resourceGlobal->toI18nAssocArray($language) : null;
        $this->params['resourceLocal'] = $resourceLocal ? $resourceLocal->toAssocArray() : null;

        $this->params['resourceGlobal'] = $resourceGlobal ? $resourceGlobal->toAssocArray() : null;
        // print_r($this->params['resourceGlobal']);
        // $this->params['update_frequencies'] = $update_frequencies;
        $this->params['subjects'] = $subjects;
        $this->params['subjectsLocal'] = $subjectsLocal;
        $this->params['countries'] = $countries;
        $this->params['selectedSubjectsGlobal'] = $selectedSubjectIdsGlobal;
        $this->params['selectedTypesGlobal'] = $selectedTypeIdsGlobal;
        $this->params['selectedCountriesGlobal'] = $selectedCountryIdsGlobal;
        $this->params['selectedSubjectsLocal'] = $selectedSubjectIdsLocal;
        $this->params['selectedTypesLocal'] = $selectedTypeIdsLocal;
        $this->params['selectedCountriesLocal'] = $selectedCountryIdsLocal;
        $this->params['selectedExternalIds'] = $selectedExternalIds;
        //$this->params['namespaces'] = $this->resourceService->getNamespaces();
        
        $this->params['licensesCount'] = $resourceGlobal && $resourceGlobal->getId() ? $this->resourceService->getLicensesCount($resourceGlobal->getId()) : 0;

        $this->params['pageTitle'] =
            ($resourceGlobal or  $resourceLocal) ? $this->resourceProvider->getText("h_res_edit", $language) :
                $this->resourceProvider->getText("h_create_db", $language);
        // Errors and status messages
        $this->params['errors'] = $errors;
        $this->params['is_created_successfully'] = array_key_exists(
            "created_successfully",
            $request->getQueryParams()
        );
        $this->params['is_updated_successfully'] = array_key_exists(
            "updated_successfully",
            $request->getQueryParams()
        );

        $this->params['is_deleted_successfully'] = array_key_exists(
            "deleted_successfully",
            $request->getQueryParams()
        );

        return $view->render(
            $response,
            'admin/edit_resource.twig',
            $this->params
        );
    }

    private function parseBodyGlobal($body): Resource
    {
        // Only one title field needed, no translation
        $title = $this->purifier->purify($body['title_global']);

        $is_free = null;
        if (array_key_exists('pricetype', $body)) {
            $is_free = (int)$body['pricetype'] == 1 ? true : false;
        }
        /*
        if ($is_free == true) {
            echo("is free");
        }
        if ($is_free == false) {
            echo("not free");
        }
        */

        $description_short = array('de' => $this->purifier->purify($body['description_short_global_de']), 'en' =>
            $this->purifier->purify($body['description_short_global_en']));
        $description = array('de' => $this->purifier->purify($body['description_global_de']), 'en' => $this->purifier->purify($body['description_global_en']));
        $report_time_start = $this->purifier->purify($body['report_time_start_global']);
        $report_time_end = $this->purifier->purify($body['report_time_end_global']);
        $publication_time_start = $this->purifier->purify($body['publication_time_start_global']);
        $publication_time_end = $this->purifier->purify($body['publication_time_end_global']);
        $is_still_updated = isset($body['is_still_updated_global']) ? (int)$body['is_still_updated_global'] : null;
        $note = array('de' => $this->purifier->purify($body['note_global_de']), 'en' => $this->purifier->purify($body['note_global_en']));
        $local_note = null;
        $isbn_issn = $this->purifier->purify($body['isbn_issn_global']);

        $shelfmark = null;
        $shelfmark_group = $body['shelfmark_global_group'];
        $shelfmark_numbers = $body['shelfmark_global_numbers'];
        if (strlen($shelfmark_group) > 0 and strlen($shelfmark_numbers) > 0) {
            $shelfmark = sprintf("%s %d", $shelfmark_group, $shelfmark_numbers);
        }

        $instructions = array('de' => $this->purifier->purify($body['instructions_global_de']), 'en' => $this->purifier->purify($body['instructions_global_en']));
        $is_visible = !isset($body['is_visible_global']);  // If the field is set, then it should be hidden (logical error in naming the checkbox)

        $types_obj = array();
        if (array_key_exists('type_global', $body)) {
            foreach ($body['type_global'] as $type_id) {
                $type_id = (int)$type_id;
                $types_obj[] = new Type($type_id);
            }
        }

        /* Not needed
        $update_frequency_obj = $body['update_frequency_global'] ?
            new UpdateFrequency((int)$body['update_frequency_global']) : null;
        */

        $subjects_obj = array();
        if (array_key_exists('subjects_global', $body)) {
            foreach ($body['subjects_global'] as $subject_id) {
                $subject_id = (int)$subject_id;
                $subjects_obj[] = new Subject($subject_id);
            }
        }

        $countries_obj = array();
        if (array_key_exists('countries_global', $body)) {
            foreach ($body['countries_global'] as $country_id) {
                $country_id = (int)$country_id;
                $countries_obj[] = new Country($country_id);
            }
        }

        $authors_obj = array();
        foreach ($_POST['author_global'] as $index => $author) {
            if (strlen($author) > 0) {
                $authors_obj[] = new Author($this->purifier->purify($author));
            }
        }

        $keywords_obj = array();
        foreach ($_POST['keyword_global_de'] as $index => $keyword_de) {
            $keyword_en = $_POST['keyword_global_en'][$index];
            $keyword_id = $_POST['keyword_global_id'][$index];
            if (strlen($keyword_de) > 0 || strlen($keyword_en) > 0) {
                $keyword = new Keyword(array('de' => $this->purifier->purify($keyword_de),
                    'en' => $this->purifier->purify($keyword_en)));

                $keyword_system = $_POST['keyword_system_global'][$index];
                if (strlen($keyword_system) > 0) {
                    $keyword->setKeywordSystem($keyword_system);
                }

                $external_id = $_POST['external_keyword_id_global'][$index];
                if (strlen($external_id) > 0) {
                    $keyword->setExternalId($external_id);
                }

                $keyword_id ? $keyword->setId((int)$keyword_id) : null;
                $keywords_obj[] = $keyword;
            }
        }
        $alternative_titles_obj = array();
        foreach ($_POST['alternative_title_global'] as $index => $keyword_de) {
            $alt_title = $this->purifier->purify($_POST['alternative_title_global'][$index]);  // No translation needed

            if (!($alt_title)) {
                continue;
            }

            $valid_from_raw = $_POST['alternative_title_valid_from_global'][$index];
            $valid_from = $valid_from_raw != "" ? new DateTime($valid_from_raw) : null;
            $valid_to_raw = $_POST['alternative_title_valid_to_global'][$index];
            $valid_to = $valid_to_raw ? new DateTime($valid_to_raw) : null;
            if (strlen($alt_title) > 0) {
                $alt_title = new AlternativeTitle($alt_title);
                if ($valid_to) {
                    $alt_title->setValidToDate($valid_to);
                }
                if ($valid_from) {
                    $alt_title->setValidFromDate($valid_from);
                }
            }
            $alternative_titles_obj[] = $alt_title;
        }

        $api_urls_obj = array();
        foreach ($_POST['api_url_global'] as $index => $api_url) {
            if (strlen($api_url) > 0) {
                $api_urls_obj[] = new Url($this->purifier->purify($api_url));
            }
        }

        $external_ids = array();
        foreach ($_POST['external_id_global'] as $index => $external_id_post) {
            $external_id = $this->purifier->purify($_POST['external_id_global'][$index]);
            if (strlen($external_id) > 0) {
                $namespace = $_POST['external_id_namespace'][$index];
                $external_id_name = $_POST['external_id_name'][$index];
                $external_id_obj = new ExternalID($namespace, $external_id, $external_id_name);
                $external_ids[] = $external_id_obj;
            }
        }

        $resource = new Resource($title, $types_obj);
        $resource->setIsFree($is_free);
        $resource->setDescriptionShort($description_short);
        $resource->setDescription($description);
        $resource->setReportTimeStart($report_time_start);
        $resource->setReportTimeEnd($report_time_end);
        $resource->setPublicationTimeStart($publication_time_start);
        $resource->setPublicationTimeEnd($publication_time_end);
        $resource->setIsStillUpdated($is_still_updated);
        // $update_frequency_obj ? $resource->setUpdateFrequency($update_frequency_obj) : null;
        $resource->setSubjects($subjects_obj);
        $resource->setAuthors($authors_obj);
        $resource->setKeywords($keywords_obj);
        $resource->setAlternativeTitles($alternative_titles_obj);
        $resource->setApiUrls($api_urls_obj);
        $resource->setCountries($countries_obj);
        $resource->setShelfmark($shelfmark);
        $resource->setNote($note);
        $resource->setLocalNote($local_note);
        $resource->setIsbnIssn($isbn_issn);
        $resource->setExternalIDs($external_ids);
        $resource->setInstructions($instructions);
        $resource->setIsVisible($is_visible);

        json_encode($resource->toAssocArray());

        return $resource;
    }

    private function parseBodyLocal($body, $localizations): Resource
    {
        $title = $this->purifier->purify($body['title_local']);

        $description_short = array('de' => $body['description_short_local_de'], 'en' =>
            $body['description_short_local_en']);
        $description = array('de' => $body['description_local_de'], 'en' => $body['description_local_en']);
        $report_time_start = $body['report_time_start_local'];
        $report_time_end = $body['report_time_end_local'];
        $publication_time_start = $body['publication_time_start_local'];
        $publication_time_end = $body['publication_time_end_local'];
        $is_still_updated = isset($body['is_still_updated_local']) ? (int)$body['is_still_updated_local'] : null;
        $note = array('de' => $body['note_local_de'], 'en' => $body['note_local_en']);
        $local_note = null;
        if (isset($body['local_note_de']) || isset($body['local_note_en'])) {
            $local_note_de = isset($body['local_note_de']) ? $body['local_note_de']: "";
            $local_note_en = isset($body['local_note_en']) ? $body['local_note_en']: "";
            $local_note = array('de' => $local_note_de, 'en' => $local_note_en);
        }
        $isbn_issn = $body['isbn_issn_local'];
        $shelfmark = null;
        $shelfmark_group = $body['shelfmark_local_group'];
        $shelfmark_numbers = $body['shelfmark_local_numbers'];
        if (strlen($shelfmark_group) > 0 and strlen($shelfmark_numbers) > 0) {
            $shelfmark = sprintf("%s %d", $shelfmark_group, $shelfmark_numbers);
        }
        $instructions = array('de' => $body['instructions_local_de'], 'en' => $body['instructions_local_en']);
        $is_visible = isset($body['is_visible_local']) ? false : true;  // If the field is set, then it should be hidden (logical error in naming the checkbox)

        $types_obj = array();
        if (array_key_exists('type_local', $body)) {
            foreach ($body['type_local'] as $type_id) {
                $type_id = (int)$type_id;
                $types_obj[] = new Type($type_id);
            }
        }

        /* Not needed
        $update_frequency_obj = $body['update_frequency_local'] ?
            new UpdateFrequency((int)$body['update_frequency_local']) : null;
        */

        $subjects_obj = array();
        if (array_key_exists('subjects_local', $body)) {
            foreach ($body['subjects_local'] as $subject_id) {
                $subject_id = (int)$subject_id;
                $subjects_obj[] = new Subject($subject_id);
            }
        }

        $countries_obj = array();
        if (array_key_exists('countries_local', $body)) {
            foreach ($body['countries_local'] as $country_id) {
                $country_id = (int)$country_id;
                $countries_obj[] = new Country($country_id);
            }
        }

        $authors_obj = array();
        foreach ($_POST['author_local'] as $index => $author) {
            if (strlen($author) > 0) {
                $authors_obj[] = new Author($author);
            }
        }
        $keywords_obj = array();
        foreach ($_POST['keyword_local_de'] as $index => $keyword_de) {
            $keyword_en = $_POST['keyword_local_en'][$index];
            $keyword_id = $_POST['keyword_local_id'][$index];
            if (strlen($keyword_de) > 0 || strlen($keyword_en) > 0) {
                $keyword = new Keyword(array('de' => $keyword_de, 'en' => $keyword_en));

                $keyword_system = $_POST['keyword_system_local'][$index];
                if (strlen($keyword_system) > 0) {
                    $keyword->setKeywordSystem($keyword_system);
                }

                $external_id = $_POST['external_keyword_id_local'][$index];
                if (strlen($external_id) > 0) {
                    $keyword->setExternalId($external_id);
                }

                $keyword_id ? $keyword->setId((int)$keyword_id) : null;
                $keywords_obj[] = $keyword;
            }
        }

        $resource = new Resource();

        if ($localizations['isTitleLocal'] == true) {
            $resource->setTitle($title);
        }
        
        if ($localizations['isTypeLocal'] == true) {
            $resource->setTypes($types_obj);
        }
    
        if ($localizations['isShortDescriptionLocal'] == true) {
            $resource->setDescriptionShort($description_short);
        }


        if ($localizations['isDescriptionLocal'] == true) {
            $resource->setDescription($description);
        }

        if ($localizations['isReportTimeLocal'] == true) {
            $resource->setReportTimeStart($report_time_start);
            $resource->setReportTimeEnd($report_time_end);
        }
        
        if ($localizations['isPublicationTimeLocal'] == true) {
            $resource->setPublicationTimeStart($publication_time_start);
            $resource->setPublicationTimeEnd($publication_time_end);
        }
        
        if ($localizations['isUpdateLocal'] == true) {
            $resource->setIsStillUpdated($is_still_updated);
        }
        
        if ($localizations['areSubjectsLocal'] == true) {
            $resource->setSubjects($subjects_obj);
        }
        
        if ($localizations['areAuthorsLocal'] == true) {
            $resource->setAuthors($authors_obj);
        }
        
        if ($localizations['areKeywordsLocal'] == true) {
            $resource->setKeywords($keywords_obj);
        }
        
        if ($localizations['areCountriesLocal'] == true) {
            $resource->setCountries($countries_obj);
        }
        
        if ($localizations['isShelfmarkLocal'] == true) {
            $resource->setShelfmark($shelfmark);
        }
        
        if ($localizations['isNoteLocal'] == true) {
            $resource->setNote($note);
        }
        
        if ($local_note) {
            $resource->setLocalNote($local_note);
        }

        $resource->setIsbnIssn($isbn_issn);
        
        if ($localizations['isInstructionLocal'] == true) {
            $resource->setInstructions($instructions);
        }
        
        $resource->setIsVisible($is_visible);

        json_encode($resource->toAssocArray());

        return $resource;
    }

    private function parseLocalizationSettings($body): array {
        $areAuthorsLocal = isset($body['authors-local']);
        $areCountriesLocal = isset($body['countries-local']);
        $isDescriptionLocal = isset($body['description-local']);
        $isInstructionLocal = isset($body['instruction-local']);
        $areKeywordsLocal = isset($body['keywords-local']);
        $isNoteLocal = isset($body['note-local']);
        $isPublicationTimeLocal = isset($body['publicationtime-local']);
        $isReportTimeLocal = isset($body['reporttime-local']);
        $isShelfmarkLocal = isset($body['shelfmark-local']);
        $isShortDescriptionLocal = isset($body['shortdescription-local']);
        $areSubjectsLocal = isset($body['subjects-local']);
        $isTitleLocal = isset($body['title-local']);
        $isTypeLocal = isset($body['type-local']);
        $isUpdateLocal = isset($body['update-local']);

        $localizationSettings = array('areKeywordsLocal' => $areKeywordsLocal, 'areAuthorsLocal' => $areAuthorsLocal, 'areCountriesLocal' => $areCountriesLocal, 'isDescriptionLocal' => $isDescriptionLocal, 'isInstructionLocal' => $isInstructionLocal, 'isNoteLocal' => $isNoteLocal, 'isPublicationTimeLocal' => $isPublicationTimeLocal, 'isReportTimeLocal' => $isReportTimeLocal, 'isShelfmarkLocal' => $isShelfmarkLocal, 'isShortDescriptionLocal' => $isShortDescriptionLocal, 'areSubjectsLocal' => $areSubjectsLocal, 'isTitleLocal' => $isTitleLocal, 'isTypeLocal' => $isTypeLocal, 'isUpdateLocal' => $isUpdateLocal);

        return $localizationSettings;
    }

    private function getLocalizationSettingsFromresource($resourceLocal): array {
        if ($resourceLocal) {
            $areAuthorsLocal = count($resourceLocal->getAuthors()) > 0;
            $areCountriesLocal = count($resourceLocal->getCountries()) > 0;
            $isDescriptionLocal = $resourceLocal->getOverwrite() && $resourceLocal->getOverwrite()->isDescriptionSet();
            $isInstructionLocal = $resourceLocal->getOverwrite() && $resourceLocal->getOverwrite()->isInstructionSet();
            $areKeywordsLocal = count($resourceLocal->getKeywords()) > 0;
            $isNoteLocal = $resourceLocal->getOverwrite() && $resourceLocal->getOverwrite()->isNoteSet(); 
            $isPublicationTimeLocal = $resourceLocal->getOverwrite() && $resourceLocal->getOverwrite()->isPublicationTimeSet();  
            $isReportTimeLocal =  $resourceLocal->getOverwrite() && $resourceLocal->getOverwrite()->isReportTimeSet();;
            $isShelfmarkLocal = $resourceLocal->getOverwrite() && $resourceLocal->getOverwrite()->isShelfmarkSet();
            $isShortDescriptionLocal = $resourceLocal->getOverwrite() && $resourceLocal->getOverwrite()->isDescriptionShortSet();
            $areSubjectsLocal = count($resourceLocal->getSubjects()) > 0;
            $isTitleLocal = $resourceLocal->getOverwrite() && $resourceLocal->getOverwrite()->isTitleSet(); 
            $isTypeLocal = count($resourceLocal->getTypes()) > 0;
            $isUpdateLocal = $resourceLocal->getOverwrite() && $resourceLocal->getOverwrite()->isUpdateSet();
        } else {
            $areAuthorsLocal = false;
            $areCountriesLocal = false;
            $isDescriptionLocal = false;
            $isInstructionLocal = false;
            $areKeywordsLocal = false;
            $isNoteLocal = false; 
            $isPublicationTimeLocal = false;  
            $isReportTimeLocal = false;
            $isShelfmarkLocal = false;
            $isShortDescriptionLocal = false;
            $areSubjectsLocal = false;
            $isTitleLocal = false; 
            $isTypeLocal = false;
            $isUpdateLocal = false;
        }
        

        $localizationSettings = array('areKeywordsLocal' => $areKeywordsLocal, 'areAuthorsLocal' => $areAuthorsLocal, 'areCountriesLocal' => $areCountriesLocal, 'isDescriptionLocal' => $isDescriptionLocal, 'isInstructionLocal' => $isInstructionLocal, 'isNoteLocal' => $isNoteLocal, 'isPublicationTimeLocal' => $isPublicationTimeLocal, 'isReportTimeLocal' => $isReportTimeLocal, 'isShelfmarkLocal' => $isShelfmarkLocal, 'isShortDescriptionLocal' => $isShortDescriptionLocal, 'areSubjectsLocal' => $areSubjectsLocal, 'isTitleLocal' => $isTitleLocal, 'isTypeLocal' => $isTypeLocal, 'isUpdateLocal' => $isUpdateLocal);

        return $localizationSettings;
    }
}
