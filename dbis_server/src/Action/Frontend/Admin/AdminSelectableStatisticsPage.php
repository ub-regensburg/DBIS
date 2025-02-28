<?php

namespace App\Action\Frontend\Admin;

use App\Domain\Organizations\OrganizationService;
use App\Domain\Resources\ResourceService;
use App\Domain\Shared\AuthService;
use App\Infrastructure\Shared\CountryProvider;
use App\Infrastructure\Shared\ResourceProvider;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Slim\Psr7\Stream;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;

class AdminSelectableStatisticsPage extends AdminBasePage
{
    protected ResourceService $resourceService;

    private $months;
    private $years;

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

        $this->months = array(
            array('value' => '01', 'de' => 'Januar', 'January'),
            array('value' => '02', 'de' => 'Februar', 'en' => 'February'),
            array('value' => '03', 'de' => 'März', 'en' => 'March'),
            array('value' => '04', 'de' => 'April', 'en' => 'April'),
            array('value' => '05', 'de' => 'Mai', 'en' => 'May'),
            array('value' => '06', 'de' => 'Juni', 'en' => 'June'),
            array('value' => '07', 'de' => 'Juli', 'en' => 'July'),
            array('value' => '08', 'de' => 'August', 'en' => 'August'),
            array('value' => '09', 'de' => 'September', 'en' => 'September'),
            array('value' => '10', 'de' => 'Oktober', 'en' => 'October'),
            array('value' => '11', 'de' => 'November', 'en' => 'November'),
            array('value' => '12', 'de' => 'Dezember', 'en' => 'December')
        );

        $currentYear = date('Y');
        $this->years = range(2007, $currentYear);
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
            return $response->withHeader('Location', '/admin/login');
        } elseif (!$this->isSuperAdmin && !$this->isAdmin && !$this->isSubjectSpecialist) {
            return $response->withHeader('Location', '/admin');
        }

        $this->params['pageTitle'] = $this->resourceProvider->getText(
            "h_selectable_statistics",
            $this->authService->getAuthenticatedUser()->getLanguage()
        );

        $organization_id = $this->getSelectedOrganizationIdFromSession();

        $params = $request->getQueryParams();

        $fromMonth = array_key_exists('from-month', $params) ? $params['from-month']: null;
        $toMonth = array_key_exists('to-month', $params) ? $params['to-month']: null;
        $fromYear = array_key_exists('from-year', $params) ? $params['from-year']: null;
        $toYear = array_key_exists('to-year', $params) ? $params['to-year']: null;
        $csvOutput = array_key_exists('csvoutput', $params) ? true: false;
        $licenseTypes = [];
        $allOrganisations = array_key_exists('all-organisations', $params) ? true : null;

        $statistics = [];
        $subjects = [];

        if ($fromMonth && $toMonth && $fromYear && $toYear) {
            $validatedDates = $this->validateAndAdjustDates($fromMonth, $fromYear, $toMonth, $toYear);
            $fromMonth = $validatedDates['startMonth'];
            $toMonth = $validatedDates['endMonth'];
            $fromYear = $validatedDates['startYear'];
            $toYear = $validatedDates['endYear'];

            if (array_key_exists('license-types', $params) && $params['license-types']) {
                $licenseTypes = array_map(function ($liceneType) {    
                    return (int) $liceneType;
                }, $params['license-types']);
            }

            if (array_key_exists('subjects', $params) && $params['subjects']) {
                $subjects = array_map(function ($subject) { 
                    list($category, $id) = explode('_', $subject);   
                    return array('is_collection' => $category == 'collection' ? true: false, 'id' => $id);
                }, $params['subjects']);
            }

            $statistics = $this->service->getSelectableStatistics($fromMonth, $toMonth, $fromYear, $toYear, $organization_id, $licenseTypes, $subjects, $allOrganisations);
        } else {
            // Default values for the dates
            $fromYear = 2024;
            $toYear = 2024;
            $fromMonth = '01';
            $toMonth = '12';
        }

        if ($csvOutput) {
            return $this->redirectStatisticsToCsvOutput($request, $response, $statistics, $fromMonth, $toMonth, $fromYear, $toYear, $organization_id);
        } else {
            return $this->renderPage($request, $response, $statistics, $fromMonth, $toMonth, $fromYear, $toYear, $organization_id, $licenseTypes, $subjects, $allOrganisations);
        }        
    }

    private function validateAndAdjustDates($startMonth, $startYear, $endMonth, $endYear) {
        // Define the minimum and maximum allowed dates
        $minDate = new \DateTime('2004-01-01'); // Minimum date: January 2004
        $maxDate = new \DateTime('December ' . date('Y')); // Maximum date: December of the current year
    
        // Create DateTime objects from input, ensuring the correct format
        $startDate = \DateTime::createFromFormat('Y-m', "$startYear-$startMonth");
        $endDate = \DateTime::createFromFormat('Y-m', "$endYear-$endMonth");
    
        // Validate the start date, adjust if it's before the minimum date
        if ($startDate < $minDate) {
            $startDate = clone $minDate;
        }
    
        // Validate the end date, adjust if it's after the maximum date
        if ($endDate > $maxDate) {
            $endDate = clone $maxDate;
        }
    
        // Ensure the start date is not after the end date
        if ($startDate > $endDate) {
            $startDate = clone $minDate;
            $endDate = clone $maxDate;
        }
    
        // Extract the adjusted month and year values
        $adjustedStartMonth = $startDate->format('m');
        $adjustedStartYear = $startDate->format('Y');
        $adjustedEndMonth = $endDate->format('m');
        $adjustedEndYear = $endDate->format('Y');
    
        return [
            'startMonth' => $adjustedStartMonth,
            'startYear' => $adjustedStartYear,
            'endMonth' => $adjustedEndMonth,
            'endYear' => $adjustedEndYear,
        ];
    }

    private function redirectStatisticsToCsvOutput($request, $response, $statistics, $fromMonth, $toMonth, $fromYear, $toYear, $organization_id) {
        // CSV file be like: DBIS-ID;Titel;Zugang;Zugriffe
        $headers = ['DBIS-Ressource_ID', 'Titel', 'Zugang', 'Zugriffe'];

        $csvData = array();
        foreach ($statistics as $fields) {
            $fields['license_type_title'] = $fields['license_type_title'] && array_key_exists($this->language, $fields['license_type_title']) ? $fields['license_type_title'][$this->language] : "";
            $csvData[] = $fields;
        }

        $currentDate = date('Y-m-d');
        $csvFile = "DBIS_Statistic_$currentDate.csv";

        $stream = fopen('php://temp', 'r+');

        fputcsv($stream, $headers);

        foreach ($csvData as $row) {
            fputcsv($stream, [
                $row['resource'],
                $row['title'],
                $row['license_type_title'],
                $row['hits']
            ]);
        }

        rewind($stream);

        $body = new Stream($stream);

        return $response->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', "attachment; filename=$csvFile")
            ->withAddedHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Cache-Control', 'post-check=0, pre-check=0')
            ->withHeader('Pragma', 'no-cache')
            ->withBody($body);
    }

    private function renderPage($request, $response, $statistics, $fromMonth, $toMonth, $fromYear, $toYear, $organization_id, $selectedLicenseTypes = [], $selectedSubjects = [], $allOrganisations = null) {
        $language = $this->language;

        $subjects = $this->resourceService->getResourceAggregatesHandledAsSubject(
            ['sort_language' => $language,
            'include_collections' => true,
            'organizationId' => $organization_id,
            'without_resources' => true]
        );
        // $subjects = $this->resourceService->getResourceAggregatesHandledAsSubject(["organizationId" => $organization_id]);
        $subjectsAssoc = array_map(function ($i) use ($language) {
            return $i->toI18nAssocArray($language);
        }, $subjects);

        $licenseTypes = $this->resourceService->getLicenseTypes(["organizationId" => $organization_id]);
        $licenseTypesAssoc = array_map(function ($i) use ($language) {
            return $i->toI18nAssocArray($language);
        }, $licenseTypes);

        $totalHits = 0;
        $usedResources = [];
        $unusedResources = [];
        foreach ($statistics as $row) {
            $totalHits += $row['hits'];

            if ($row['hits'] == 0) {
                $unusedResources[$row['resource']] = true;
            } else {
                $usedResources[$row['resource']] = true;
            }
        }

        $usedCount = count($usedResources);
        $unusedCount = count($unusedResources);
        $totalResources = $usedCount + $unusedCount;

        $this->params['from_month'] = $fromMonth;
        $this->params['to_month'] = $toMonth;
        $this->params['from_year'] = $fromYear;
        $this->params['to_year'] = $toYear;
        $this->params['statistics'] = $statistics;
        $this->params['total_hits'] = number_format($totalHits, 0, ',', '.');
        $this->params['no_hits'] = number_format($unusedCount, 0, ',', '.');
        $this->params['used_resources'] = number_format($usedCount, 0, ',', '.');
        $this->params['total_resources'] = number_format($totalResources, 0, ',', '.');
        $this->params['months'] = $this->months;
        $this->params['years'] = $this->years;
        $this->params['ubrId'] = $organization_id;

        $this->params['subjects'] = $subjectsAssoc;
        $this->params['licenseTypes'] = $licenseTypesAssoc;
        $this->params['selectedLicenseTypes'] = $selectedLicenseTypes;
        $this->params['selectedSubjects'] = $selectedSubjects;
        $this->params['allOrganisationsSelected'] = is_null($allOrganisations) ? false: true;

        $view = Twig::fromRequest($request);

        return $view->render(
            $response,
            'admin/statistics/selectable.twig',
            $this->params
        );
    }
}
