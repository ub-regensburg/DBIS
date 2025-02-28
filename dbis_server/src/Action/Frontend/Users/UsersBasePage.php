<?php

declare(strict_types=1);

namespace App\Action\Frontend\Users;

use App\Domain\Organizations\Exceptions\OrganizationWithUbrIdNotExistingException;
use App\Domain\Resources\ResourceService;
use App\Domain\Organizations\OrganizationService;
use App\Infrastructure\Shared\ResourceProvider;
use App\Infrastructure\Shared\ContextProvider;
use App\Domain\Organizations\Entities\Organization;
use App\Domain\Organizations\Exceptions\OrganizationWithIpNotExistingException;

/**
 * UsersBasePage
 *
 * Abstract class for the front end view of DBIS.
 */
abstract class UsersBasePage extends \App\Action\Frontend\BasePage
{
    /** @var ResourceProvider */
    protected ResourceProvider $resourceProvider;

    /** @var ResourceService */
    protected ResourceService $service;

    protected ContextProvider $contextProvider;

    /** The currently selected organization
     * @var Organization|null
     */
    protected ?Organization $organization;

    /** The currently selected organization
     * @var array|null
     */
    protected ?array $organizationI18n = null;

    /** A matching organization for which a user may have network access
     * @var array|null
     */
    protected ?array $organizationWithNetworkAccess = null;

    /** @var array */
    protected array $organizations;

    /** @var OrganizationService */
    protected OrganizationService $organizationService;

    /**
     * @var string
     */
    protected string $language;

    /**
     * @var array
     */
    protected array $params;

    /**
     * @throws OrganizationWithIpNotExistingException
     * @throws OrganizationWithUbrIdNotExistingException
     */
    public function __construct(
        ResourceProvider $rp,
        ResourceService $service,
        OrganizationService $os,
        ContextProvider $contextProvider
    ) {
        $this->contextProvider = $contextProvider;
        $this->resourceProvider = $rp;
        $this->service = $service;
        $this->organizationService = $os;

        $this->language = $_SESSION["language"] ?? "de";

        if (isset($_GET["lang"])) {
            $this->language = htmlspecialchars($_GET['lang']) == 'en' ? 'en' : 'de';
            $_SESSION["language"] = $this->language;
        }       

        // Get the organisation that is saved in the session
        $selectedOrgId = $this->getSelectedOrganizationIdFromSession();

        
        $ip = $_SERVER["REMOTE_ADDR"];

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        
        // Get the organisation for the specific ip address
        $orgIdForIp = $this->getOrganizationIdByIp($ip);

        if ($selectedOrgId) {
            try {
                $this->organization = $this->organizationService
                    ->getOrganizationByUbrId($selectedOrgId);
                $this->organizationI18n = $this->organization->toI18nAssocArray($this->language);
            } catch (OrganizationWithUbrIdNotExistingException $ex) {
                $this->clearSelectedOrganization();
                
                // Is this needed?
                if ($orgIdForIp) {
                    $this->setSelectedOrganization($orgIdForIp);
                }
            }
        } elseif ($orgIdForIp) {
            $this->setSelectedOrganization($orgIdForIp);
        } else {
            $this->setSelectedOrganization('ALL');
        }

        /*
         * In case the ip address is different from the selected org.
         */
        /*
        if ($orgIdForIp) {
            $this->organizationWithNetworkAccess = $this
                ->organizationService
                ->getOrganizationByIp($orgIdForIp)
                ->toI18nAssocArray($this->language);
        }
        */
        
        $organizations = $this->groupOrganizationsByCity(array_map(function (Organization $o) {
            return $o->toI18nAssocArray($this->language);
        }, $this->organizationService->getOrganizations(array('hasDbisView' => true))));

        $this->params = [
            'language' => $this->language,
            'i18n' => $this->resourceProvider->getAssocArrayForLanguage($this->language),
            'pageTitle' => "No title, please change",
            'organization' => $this->organizationI18n,
            'organizationWithNetworkAccess' => $this->organizationWithNetworkAccess,
            'organizationsGroupedByCity' => $organizations
        ];
    }

    protected function doesOrganizationHasCollections(): bool
    {
        $organization_id = $this->getSelectedOrganizationIdFromSession();

        if ($organization_id == null) {
            return false;
        }

        $collections = $this->service->getCollections(
            ['organizationId' => $organization_id,
                'only_visibles' => true,
                'only_subjects' => false]
        );

        return !empty($collections);
    }

    protected function setSelectedOrganization(?string $orgId): void
    {
        if (!is_null($orgId) && strlen($orgId) > 0) {
            try {
                $_SESSION["ubrId"] = $orgId;

                $this->organization =
                    $this->organizationService
                        ->getOrganizationByUbrId($orgId);
                $this->organizationI18n = $this->organization->toI18nAssocArray($this->language);
                $this->params['organization'] = $this->organizationI18n;
            } catch (OrganizationWithUbrIdNotExistingException $ex) {
                // TODO: Maybe do something ...
            }
        } else {
            $this->clearSelectedOrganization();
        }
    }

    protected function clearSelectedOrganization(): void
    {
        unset($_SESSION['ubrId']);
        $this->organization = null;
        $this->organizationI18n = null;
        $this->params['organization'] = null;
    }

    protected function getSelectedOrganizationIdFromSession(): ?string
    {
        return $_SESSION["ubrId"] ?? null;
    }

    protected function getOrganizationIdByIp(string $ip): ?string
    {
        $TEST = False;

        if ($TEST) {
            $org = $this->organizationService
            ->getOrganizationByIp("132.199.243.28")
            ->toI18nAssocArray($this->language);
            
            if ($org) {
                return $org["ubrId"];
            } else {
                return null;
            }
        } else {
            try {   
                $org = $this->organizationService
                ->getOrganizationByIp($ip)
                ->toI18nAssocArray($this->language);

                return $org["ubrId"];
            } catch (OrganizationWithIpNotExistingException $e) {
                return null;
            }
        }
    }

    /*
     * Returns access, license can be accesses via key "license"
     */
    protected function sortAccessesByEstimatedValue(array $licenses): array
    {
        // This order is based on the id of licenses & accesses and will be replaced in a future issue
        $orderWithVpnAccess = ["1_1", "2_8", "3_8", "2_4", "2_5", "2_6", "3_4", "3_5", "3_6", "5_9", "1_2", "2_7", "3_7", "4_3"];
        $orderWithoutVpnAccess = ["1_1", "2_4", "2_5", "2_6", "3_4", "3_5", "3_6", "2_8", "3_8", "5_9", "1_2", "2_7", "3_7", "4_3"];
        $accessOrder = $this->organizationWithNetworkAccess != null ? $orderWithVpnAccess : $orderWithoutVpnAccess;

        // Transform data to array of format [license, access]
        $accesses = array_reduce($licenses, function ($carry, $license) {
            $unwound = array_map(function ($access) use ($license) {
                return ["license" => $license, "access" => $access];
            }, $license['accesses']);
            return array_merge($carry, $unwound);
        }, []);

        // Sort data by accessOrder
        usort($accesses, function ($a, $b) use ($accessOrder) {
            if (array_key_exists('isMainAccess', $a["access"]) && array_key_exists('isMainAccess', $b["access"])) {
                if ($a['access']['isMainAccess'] && !$b['access']['isMainAccess']) {
                    return -1;  // $a has true, put it first
                } elseif (!$a['access']['isMainAccess'] && $b['access']['isMainAccess']) {
                    return 1;   // $b has true, put it first
                }
            }

            if ($a["access"]["type"] && array_key_exists('id', $a["access"]["type"]) && $b["access"]["type"] && array_key_exists('id', $b["access"]["type"])) {
                $aKey = $a["license"]["type"]["id"] . "_" . $a["access"]["type"]["id"];
                $bKey = $b["license"]["type"]["id"] . "_" . $b["access"]["type"]["id"];
                // Assign a really big number, if the combo is illegal
                $aVal = in_array($aKey, $accessOrder) ? array_search($aKey, $accessOrder) : 99999;
                $bVal = in_array($bKey, $accessOrder) ? array_search($bKey, $accessOrder) : 99999;
            } else {
                if ($a["access"]["type"] && array_key_exists('id', $a["access"]["type"]) && (!$b["access"]["type"] || !array_key_exists('id', $b["access"]["type"]))) {
                    $aVal = 9999;
                    $bVal = 99999;
                } elseif ($b["access"]["type"] && array_key_exists('id', $b["access"]["type"]) && (!$a["access"]["type"] || !array_key_exists('id', $a["access"]["type"]))) {
                    $aVal = 99999;
                    $bVal = 9999;
                } else {
                    $aVal = 99999;
                    $bVal = 99999;
                }
                
            }
            
            return $aVal - $bVal;
        });

        // Transform data back, give access to license
        return array_map(function ($item) {
            $item['access']['license'] = $item['license'];
            return $item['access'];
        }, $accesses);
    }

    protected function extractMostValuableAccess(array $licenses)
    {
        $mostValuableAccess = [];
        $mostValuableAccessValue = "";
        // This order is based on the id of license types & access types and will be replaced in a future issue
        $orderWithVpnAccess = ["1_1", "2_8", "3_8", "2_4", "2_5", "2_6", "3_4", "3_5", "3_6", "5_9", "1_2", "2_7", "3_7", "4_3"];
        $orderWithoutVpnAccess = ["1_1", "2_4", "2_5", "2_6", "3_4", "3_5", "3_6", "2_8", "3_8", "5_9", "1_2", "2_7", "3_7", "4_3"];
        // TODO: FID licenses and remote access licenses

        $accessOrder = $this->organizationWithNetworkAccess != null ? $orderWithVpnAccess : $orderWithoutVpnAccess;

        $accessesCount = 0;

        foreach ($licenses as $lic) {
            if (array_key_exists('accesses', $lic) && !is_null($lic["accesses"])) {
                foreach ($lic["accesses"] as $acc) {
                    if ($lic && $acc) {

                        $accessesCount++;

                        $lic_type_id = $lic["type"];
                        $acc_type_id = $acc["type"];

                        if (array_key_exists('isMainAccess', $acc) && $acc["isMainAccess"] == true) {
                            $mostValuableAccess = $lic;
                            $mostValuableAccess["accesses"] = [$acc];
                            // If access is marked as main access, then return immediatly
                            return $mostValuableAccess;
                        }
    
                        $currentValue = $lic_type_id . "_" . $acc_type_id;
                        // During the first iteration, set the first value
                        // so that from now on, comparisons can be made
                        if ($mostValuableAccessValue == "") {
                            $mostValuableAccess = $lic;
                            $mostValuableAccess["accesses"] = [$acc];
                            $mostValuableAccessValue = $currentValue;
                        }
    
                        if (array_search($currentValue, $accessOrder) < array_search($mostValuableAccessValue, $accessOrder)) {
                            $mostValuableAccess = $lic;
                            $mostValuableAccess["accesses"] = [$acc];
                            $mostValuableAccessValue = $currentValue;
                        }
                    }
                }
                // echo("</br>");
            }
        }

        if ($accessesCount == 1) {
            return $mostValuableAccess;
        } else {
            return false;
        }
    }

    protected function determineMostValuableAccesses(array $resources): array
    {
        $array = [];
        foreach ($resources as $resource) {
            $resource["most_valuable_access"] = $this->extractMostValuableAccess($resource["licenses"]);

            $array[] = $resource;
        }
        return $array;
    }

    protected function getResourceListIds(array $resources): array
    {
        $array = [];
        foreach ($resources as $res) {
            $array[] = $res["resource_id"];
        }
        return $array;
    }

    protected function determineTrafficLights(array $resources): array
    {
        $array = [];
        foreach ($resources as $resource) {
            $is_free = $resource['is_free'];
            $resource["traffic_light"] = $this->extractTrafficLight($is_free, $resource["licenses"]);

            $array[] = $resource;
        }
        return $array;
    }

    protected function determineTrafficLight(array $resource, bool $isElasticResult = true): array
    {
        $is_free = $resource['is_free'];
        $resource["traffic_light"] = $this->extractTrafficLight($is_free, $resource["licenses"]);
        return $resource;
    }
}
