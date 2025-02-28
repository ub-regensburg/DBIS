<?php

namespace App\Infrastructure\Shared;

use App\Action\Frontend\Users\UsersWarpTo;
use App\Domain\Organizations\Entities\Organization;
use App\Domain\Resources\Entities\Access;
use App\Domain\Resources\Entities\AccessMapping;
use App\Domain\Resources\Entities\Collection;
use App\Domain\Resources\Entities\Country;
use App\Domain\Resources\Entities\License;
use App\Domain\Resources\Entities\LicenseType;
use App\Domain\Resources\Entities\PublicationForm;
use App\Domain\Resources\Entities\Resource;
use App\Domain\Resources\Entities\Subject;
use App\Domain\Resources\Entities\Type;

error_reporting(E_ALL & ~E_DEPRECATED);

class XMLGenerator extends \App\Action\Frontend\BasePage
{
    public function __construct()
    {
    }

    const DBIS_PAGE_XSD_URL = 'https://dbis.ur.de/dbinfo/scheme/dbis_page_output.xsd';

    public function generateResource($resource, $organization, $lang='de') {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->appendChild(new \DOMElement('dbis_page'));
        $root->setAttributeNode(new \DOMAttr('xsi:noNamespaceSchemaLocation', self::DBIS_PAGE_XSD_URL));
        $root->setAttributeNode(new \DOMAttr('version', '1.0.0'));
        $root->setAttributeNode(new \DOMAttr('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance'));

        if ($organization) {
            $library = $dom->createElement("library", $organization['name']);
        } else {
            $library = $dom->createElement("library", "Gesamtbestand");
        }
        
        $library = $root->appendChild($library);

        if ($organization) {
            $library->setAttributeNode(new \DOMAttr('bib_id', $organization['dbisId']));
        } else {
            $library->setAttributeNode(new \DOMAttr('bib_id', ''));
        }

        $page_vars = $root->appendChild(new \DOMElement('page_vars'));
        if ($organization) {
            $bib_id = $dom->createElement("bib_id", $organization['dbisId']);
        } else {
            $bib_id = $dom->createElement("bib_id");
        }
        $page_vars->appendChild($bib_id);

        $title_id = $dom->createElement("titel_id", $resource['id']);
        $page_vars->appendChild($title_id);
        
        $colors = $dom->createElement("colors");
        $page_vars->appendChild($colors);

        $ocolors = $dom->createElement("ocolors");
        $page_vars->appendChild($ocolors);

        $details = $dom->createElement("details");
        $details = $root->appendChild($details);

        $titles = $dom->createElement("titles");
        $titles = $root->appendChild($titles);
        $title = $dom->createElement("title", $resource['title']);
        $title->setAttributeNode(new \DOMAttr('main', 'Y'));
        $titles = $titles->appendChild($title);

        $db_access_info = $dom->createElement("db_access_info");
        $db_access_info = $root->appendChild($db_access_info);

        # TODO: Main access
        if (count($resource['licenses']) > 0 ) {
            if (count($resource['licenses'][0]['accesses']) > 0) {
                $main_access = $resource['licenses'][0]['accesses'][0];

                $access = $dom->createElement("db_access", $main_access['type']['title']);
                $access = $dom->createElement("db_access_short_text", $main_access['type']['description']);
                $access = $dom->createElement("db_access_long_text", $main_access['type']['description']);
            }
        }

        $accesses = $dom->createElement("accesses");
        $accesses = $root->appendChild($accesses);

        foreach ($resource['licenses'] as &$license) {
            $access_type = $license['type']['isGlobal'] ? 'free': 'lic';
            foreach ($license['accesses'] as &$access_obj) {
                $access = $dom->createElement("access", $access_obj['type']['title']);
                $access->setAttributeNode(new \DOMAttr('href', $access_obj['accessUrl']));
                $access->setAttributeNode(new \DOMAttr('type', $access_type));
                $accesses = $accesses->appendChild($access);
            }
        }

        $description = htmlspecialchars($resource['description']);
        $content = $dom->createElement("content", $description);
        $content = $root->appendChild($content);

        $subjects = $dom->createElement("subjects");
        $subjects = $root->appendChild($subjects);

        foreach ($resource['subjects'] as &$subject) {
            $subject = $dom->createElement("subject", $subject['title']);
            $subjects = $subjects->appendChild($subject);
        }

        $keywords = $dom->createElement("keywords");
        $keywords = $root->appendChild($keywords);

        foreach ($resource['keywords'] as &$keyword) {
            $keyword = $dom->createElement("keyword", $keyword['title']);
            $keywords = $keywords->appendChild($keyword);
        }

        $appearence = $dom->createElement("appearence");
        $appearence = $root->appendChild($appearence);

        $db_type_infos = $dom->createElement("db_type_infos");
        $db_type_infos = $root->appendChild($db_type_infos);

        foreach ($resource['types'] as &$type) {
            $db_type_info = $dom->createElement("db_type_info");
            $db_type_info->setAttributeNode(new \DOMAttr('db_type_id', 'db_type_id_' . $type['id']));

            $db_type = $dom->createElement("db_type", $type['title']);
            // $db_type_info = $db_type_info->appendChild($db_type);
            $db_type_info->appendChild($db_type);

            $db_type_long_text = $dom->createElement("db_type_long_text", $type['description']);
            // $db_type_info = $db_type_info->appendChild($db_type_long_text);
            $db_type_info->appendChild($db_type_long_text);

            $db_type_infos = $db_type_infos->appendChild($db_type_info);
        }

        return $dom->saveXML(); 
    }

    public function generatePlaintextWrapper($text=''): bool|string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dbis_page = new \DOMElement('dbis_page');
        $root = $dom->appendChild($dbis_page);
        $root->setAttributeNode(new \DOMAttr('xsi:noNamespaceSchemaLocation', self::DBIS_PAGE_XSD_URL));
        $root->setAttributeNode(new \DOMAttr('version', '1.0.0'));
        $root->setAttributeNode(new \DOMAttr('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance'));

        $dbis_page->textContent = $text;

        return $dom->saveXML();
    }

    public function generateResourceForLegacyDetailPage(Resource $resourceGlobal, Resource $resourceLocal=null, $organization=null, AccessMapping $access_mapping=null, $lang='de'): bool|string
    {
        if (is_null($resourceLocal)) {
            $resourceLocal = $resourceGlobal;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->appendChild(new \DOMElement('dbis_page'));
        $root->setAttributeNode(new \DOMAttr('xsi:noNamespaceSchemaLocation', self::DBIS_PAGE_XSD_URL));
        $root->setAttributeNode(new \DOMAttr('version', '1.0.0'));
        $root->setAttributeNode(new \DOMAttr('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance'));

        if ($organization) {
            $library = $dom->createElement("library", $organization['name']);
        } else {
            $library = $dom->createElement("library", "Gesamtbestand");
        }

        $library = $root->appendChild($library);

        if ($organization) {
            $library->setAttributeNode(new \DOMAttr('bib_id', $organization['dbisId']));
        } else {
            $library->setAttributeNode(new \DOMAttr('bib_id', ''));
        }

        $page_vars = $root->appendChild(new \DOMElement('page_vars'));
        if ($organization) {
            $bib_id = $dom->createElement("bib_id", $organization['dbisId']);
        } else {
            $bib_id = $dom->createElement("bib_id");
        }
        $page_vars->appendChild($bib_id);

        $title_id = $dom->createElement("titel_id", $resourceLocal->getId());
        $page_vars->appendChild($title_id);

        $headline = $dom->createElement("headline", "Detailansicht");
        $root->appendChild($headline);

        $details = $dom->createElement("details");
        $root->appendChild($details);

        $titles = $dom->createElement("titles");
        $details->appendChild($titles);
        $title = $dom->createElement("title");
        $title->appendChild($dom->createTextNode(html_entity_decode($resourceLocal->getTitle() ?? $resourceGlobal->getTitle())));
        $title->setAttributeNode(new \DOMAttr('main', 'Y'));
        $titles->appendChild($title);
        {
            $id_to_at = self::aggregateByIdLocalOverGlobal($resourceGlobal->getAlternativeTitles(), $resourceLocal->getAlternativeTitles());
            foreach ($id_to_at as $alternative_title) {
                $title = $dom->createElement("title");
                $title->appendChild($dom->createTextNode(html_entity_decode($alternative_title->getTitle())));
                $title->setAttributeNode(new \DOMAttr('main', 'N'));
                $titles->appendChild($title);
            }
        }

        // scan all licenses of this resource for accesses
        $access_id = null; // use the first access that we find
        $appearace_text = "";
        $publisher_text = "(not found in license data)";
        $license_external_notes = "";

        {
            $id_to_license = self::aggregateByIdLocalOverGlobal($resourceGlobal->getLicenses(), $resourceLocal->getLicenses());
            foreach ($id_to_license as $license) {
                foreach ($license->getAccesses() as $access) {
                    // if first occurrence ...
                    if (!isset($db_access_info_created)) {
                        // ... then also create and append an appropriate "db_access_info" node
                        $db_access_info_created = true;

                        $access_id = $access_mapping ? $access_mapping->getPrefixedZugangId() : $access->getId();
                        $db_access_text = $access->getLabel()[$lang] ?? '';
                        $db_access_short_text = $access->getLongLabel()[$lang] ?? '';
                        $db_access_long_text = $access->getLongestLabel()[$lang] ?? '';

                        $db_access_info = $dom->createElement("db_access_info");
                        $db_access_info->setAttributeNode(new \DOMAttr('access_id', $access_id));
                        $db_access_info->appendChild(new \DOMElement('db_access', $db_access_text));
                        $db_access_info->appendChild(new \DOMElement('db_access_short_text', $db_access_short_text));
                        if ($db_access_long_text) {
                            $db_access_info->appendChild(new \DOMElement('db_access_long_text', $db_access_long_text));
                        }

                        /*
                        // TODO: handle multiple licenses and accesses
                        $licenseType = $license['type'];
                        if ($licenseType) {
                            $db_access_info->setAttributeNode(new \DOMAttr('license_type', $licenseType));
                        }
                        $licenseForm = $license['form'];
                        if ($licenseForm) {
                            $db_access_info->setAttributeNode(new \DOMAttr('license_form', $licenseForm));
                        }
                        $accessType = $access['type'];
                        if ($accessType) {
                            $db_access_info->setAttributeNode(new \DOMAttr('access_type', $accessType));
                        }
                        $accessForm = $access['form'];
                        if ($accessForm) {
                            $db_access_info->setAttributeNode(new \DOMAttr('access_form', $accessForm));
                        }
                        */
                        $traffic_light = $this->extractTrafficLight($resourceLocal->isFree() ?? $resourceGlobal->isFree(), $id_to_license);
                        if ($traffic_light) {
                            $db_access_info->setAttributeNode(new \DOMAttr('traffic_light', $traffic_light));
                        }
                        $details->appendChild($db_access_info);

                        $accesses = $dom->createElement("accesses");
                        $details->appendChild($accesses);
                        $access_node = $dom->createElement("access");
                        $access_node->setAttributeNode(new \DOMAttr('main', 'Y'));
                        $access_node->setAttributeNode(new \DOMAttr('type', 'lic'));
                        $accessUrl = $access->getAccessUrl();
                        $accessUrl = UsersWarpTo::buildUrl($accessUrl, $resourceLocal->getId(), $access->getId(), 
                            $organization ? $organization['ubrId'] : null,
                            $license?->getType()?->getId(), $license?->getForm()?->getId(),
                            $access?->getType()?->getId(), $access?->getForm()?->getId());
                        $access_node->setAttributeNode(new \DOMAttr('href', $accessUrl));
                        $accesses->appendChild($access_node);

                        $appearace_text = $license->getPublicationForm()?->getTitle()[$lang] ?? '';
                        $publisher_text = $license->getPublisher()?->getTitle() ?? $publisher_text;
                        $license_external_notes = $license->getExternalNotes()[$lang] ?? '';
                    }
                }
            }
        }

        if (!$license_external_notes) {
            // TODO: this is fishy - where should the text for 'hints' actually be sourced from?
            $maybe_overwritten_note = $resourceLocal?->getOverwrite()?->getNote() ?? $resourceLocal?->getNote() ?? $resourceGlobal->getNote();
            $license_external_notes = $maybe_overwritten_note ? $maybe_overwritten_note[$lang] : '';
        }
        $hints = $dom->createElement("hints");
        $hints->appendChild($dom->createCDATASection(html_entity_decode($license_external_notes)));
        $details->appendChild($hints);

        $descriptions = $resourceLocal?->getOverwrite()?->getDescription() ?? $resourceLocal->getDescription() ?? $resourceGlobal->getDescription();
        if (isset($descriptions['de'])) {
            $content_de = $dom->createElement("content");
            $content_de->appendChild($dom->createCDATASection(html_entity_decode($descriptions['de'])));
            $details->appendChild($content_de);
        }
        if (isset($descriptions['en'])) {
            $content_en = $dom->createElement("content_eng");
            $content_en->appendChild($dom->createCDATASection(html_entity_decode($descriptions['en'])));
            $details->appendChild($content_en);
        }

        $instructions = $resourceLocal?->getOverwrite()?->getInstructions() ?? $resourceLocal?->getInstructions() ?? $resourceGlobal->getInstructions();
        if (isset($instructions['de'])) {
            $instruction_de = $dom->createElement("instruction");
            $instruction_de->appendChild($dom->createCDATASection($instructions['de']));
            $details->appendChild($instruction_de);
        }
        if (isset($instructions['en'])) {
            $instruction_en = $dom->createElement("instruction_eng");
            $instruction_en->appendChild($dom->createCDATASection($instructions['en']));
            $details->appendChild($instruction_en);
        }

        {
            $subjects = $dom->createElement("subjects");
            $details->appendChild($subjects);
            $subjectList = $resourceLocal->getSubjects() ?: $resourceGlobal->getSubjects();
            foreach ($subjectList as &$subject) {
                $dom_subject = $dom->createElement("subject", htmlspecialchars($subject->getTitle()[$lang]));
                $dom_subject->setAttributeNode(new \DOMAttr('collection', 'false'));
                $subjects->appendChild($dom_subject);
            }
            $collectionList = $resourceLocal->getCollections() ?: $resourceGlobal->getCollections();
            foreach ($collectionList as &$collection) {
                $dom_subject = $dom->createElement("subject", htmlspecialchars($collection->getTitle()[$lang]));
                $dom_subject->setAttributeNode(new \DOMAttr('collection', 'true'));
                $subjects->appendChild($dom_subject);
            }
        }

        {
            $keywordList = $resourceLocal->getKeywords() ?: $resourceGlobal->getKeywords();
            $keywords = $dom->createElement("keywords");
            $details->appendChild($keywords);
            foreach ($keywordList as &$keyword) {
                $dom_keyword = $dom->createElement("keyword");
                $dom_keyword->appendChild($dom->createTextNode(htmlspecialchars($keyword->getTitle()[$lang])));
                $keywords->appendChild($dom_keyword);
            }
        }

        $appearance = $dom->createElement("appearence"); // that typo is "legacy code"
        $appearance->appendChild($dom->createTextNode($appearace_text));
        $details->appendChild($appearance);

        {
            $id_to_type = self::aggregateByIdLocalOverGlobal($resourceGlobal->getTypes(), $resourceLocal->getTypes());
            $db_type_infos = $dom->createElement("db_type_infos");
            $details->appendChild($db_type_infos);
            foreach ($id_to_type as &$type) {
                $db_type_info = $dom->createElement("db_type_info");
                $db_type_info->setAttributeNode(new \DOMAttr('db_type_id', 'db_type_'.Type::newIdToOldId($type->getId())));
                $db_type = $dom->createElement("db_type", $type->getTitle()[$lang]);
                $db_type_info->appendChild($db_type);
                $db_type_long_text = $dom->createElement("db_type_long_text", $type->getDescription()[$lang]);
                $db_type_info->appendChild($db_type_long_text);
                $db_type_infos->appendChild($db_type_info);
            }
        }

        $publisher = $dom->createElement("publisher");
        $publisher->appendChild($dom->createTextNode(html_entity_decode($publisher_text)));
        $details->appendChild($publisher);

        $report_time_start = $resourceLocal?->getOverwrite()?->getReportTimeStart() ?? $resourceLocal->getReportTimeStart() ?? $resourceGlobal->getReportTimeStart();
        $report_time_end = $resourceLocal?->getOverwrite()?->getReportTimeEnd() ?? $resourceLocal->getReportTimeEnd() ?? $resourceGlobal->getReportTimeEnd();
        if ($report_time_start || $report_time_end) {
            $report_periods = $dom->createElement("report_periods");
            $report_periods->appendChild($dom->createElement("report_time_start", $report_time_start ?? ''));
            $report_periods->appendChild($dom->createElement("report_time_end", $report_time_end ?? ''));
            $details->appendChild($report_periods);
        }

        // $resource_global_dump = print_r($resourceGlobal, true);
        // $resource_local_dump = print_r($resourceLocal, true);
        // $debug_node = $root->appendChild($dom->createElement("debug"));
        // $debug_node->appendChild($dom->createCDATASection($resource_global_dump));
        // $debug_node->appendChild($dom->createCDATASection($resource_local_dump));

        return $dom->saveXML();
    }

    public static function aggregateByIdLocalOverGlobal(array $global, array $local): array
    {
        $id_to_value = [];
        foreach (array_merge($local, $global) as &$value) {
            if (!isset($id_to_value[$value->getId()])) {
                $id_to_value[$value->getId()] = $value;
            }
        }
        return $id_to_value;
    }

    public function generateResources($resources, $organization, $lang='de') {

    }

    public function generateSubjects($subjects, $organization, $lang='de') {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->appendChild(new \DOMElement('dbis_page'));
        $root->setAttributeNode(new \DOMAttr('xsi:noNamespaceSchemaLocation', self::DBIS_PAGE_XSD_URL));
        $root->setAttributeNode(new \DOMAttr('version', '1.0.0'));
        $root->setAttributeNode(new \DOMAttr('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance'));

        if ($organization) {
            $library = $dom->createElement("library", $organization['name']);
        } else {
            $library = $dom->createElement("library", "Gesamtbestand");
        }

        $library = $root->appendChild($library);

        if ($organization) {
            $library->setAttributeNode(new \DOMAttr('bib_id', $organization['dbisId']));
        } else {
            $library->setAttributeNode(new \DOMAttr('bib_id', ''));
        }

        $page_vars = $root->appendChild(new \DOMElement('page_vars'));
        if ($organization) {
            $bib_id = $dom->createElement("bib_id", $organization['dbisId']);
        } else {
            $bib_id = $dom->createElement("bib_id");
        }
        $page_vars->appendChild($bib_id);

        $headline_text = $lang == 'de' ? 'Fachübersicht': 'Subject list';
        $headline = $dom->createElement("headline", $headline_text);
        $library = $root->appendChild($headline);

        $list_subjects_collections = $dom->createElement("list_subjects_collections");
        $list_subjects_collections = $root->appendChild($list_subjects_collections);

        foreach ($subjects as $key=>&$subject) {
            $list_subjects_collections_item = $dom->createElement("list_subjects_collections_item", $subject['title']);
            $list_subjects_collections_item = $list_subjects_collections->appendChild($list_subjects_collections_item);

            $lett = $subject['is_collection'] ? 'c' : 'f';
            $number = $key + 1;

            $list_subjects_collections_item->setAttributeNode(new \DOMAttr('notation', $subject['id']));  // id of subject or collection
            $list_subjects_collections_item->setAttributeNode(new \DOMAttr('number', $number));  // number in the listing
            $list_subjects_collections_item->setAttributeNode(new \DOMAttr('lett', $lett));  // if collection, then "c", else "f"
        }

        return $dom->saveXML();
    }

    public function generateSubjectsForLegacyFachlistePage($subjects, array $resourcesBySubject, ?Organization $organization, $lang='de') {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->appendChild(new \DOMElement('dbis_page'));
        $root->setAttributeNode(new \DOMAttr('xsi:noNamespaceSchemaLocation', self::DBIS_PAGE_XSD_URL));
        $root->setAttributeNode(new \DOMAttr('version', '1.0.0'));
        $root->setAttributeNode(new \DOMAttr('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance'));

        if ($organization) {
            $library = $dom->createElement("library", $organization->getName()[$lang]);
        } else {
            $library = $dom->createElement("library", "Gesamtbestand");
        }

        $library = $root->appendChild($library);

        if ($organization) {
            $library->setAttributeNode(new \DOMAttr('bib_id', $organization->getDbisId()));
        } else {
            $library->setAttributeNode(new \DOMAttr('bib_id', ''));
        }

        $page_vars = $root->appendChild(new \DOMElement('page_vars'));
        if ($organization) {
            $bib_id = $dom->createElement("bib_id", $organization->getDbisId());
        } else {
            $bib_id = $dom->createElement("bib_id");
        }
        $page_vars->appendChild($bib_id);

        $headline_text = $lang == 'de' ? 'Fachübersicht': 'Subject list';
        $headline = $dom->createElement("headline", $headline_text);
        $root->appendChild($headline);

        $list_subjects_collections = $dom->createElement("list_subjects_collections");
        $root->appendChild($list_subjects_collections);

        /* @var $subject Subject|Collection */
        foreach ($subjects as $key=>&$subject) {
            if ($subject->isCollection() && !$subject->isVisible()) {
                    continue;
            }

            $list_subjects_collections_item = $dom->createElement("list_subjects_collections_item", htmlspecialchars($subject->getTitle()[$lang]));
            $list_subjects_collections->appendChild($list_subjects_collections_item);

            if ($subject->isCollection()) {
                $lett = 'c';
                $notation = $subject->getNotation() ?: $subject->getId();
            } else {
                $lett = 'f';
                $notation = $subject->getId();
            }

            $list_subjects_collections_item->setAttributeNode(new \DOMAttr('lett', $lett));
            $list_subjects_collections_item->setAttributeNode(new \DOMAttr('notation', $notation));  // id of subject or collection (alt: fach.fach_id, neu: subject.id)
            if (isset($resourcesBySubject[$subject->getId()])) {
                $number = $resourcesBySubject[$subject->getId()];
                $list_subjects_collections_item->setAttributeNode(new \DOMAttr('number', $number));
            }
            $list_subjects_collections_item->setAttributeNode(new \DOMAttr($subject->isCollection() ? 'collection_id' : 'subject_id', $subject->getId()));
        }

        //$dump = print_r($resourcesBySubject, true);
        //$dump = "\n".$dump."\n";
        //$debug_node = $root->appendChild($dom->createElement("debug"));
        //$debug_node->appendChild($dom->createCDATASection($dump));

        return $dom->saveXML();
    }

    /**
     * @param $subjects
     * @param Type[] $dbTypes
     * @param LicenseType[] $licenseTypes
     * @param PublicationForm[] $publicationForms
     * @param array $countries
     * @param $organization
     * @param $lang
     * @return false|string
     * @throws \DOMException
     */
    public function generateSubjectsForLegacySuchePage($subjects, array $dbTypes, array $licenseTypes, array $publicationForms, array $countries, $organization, $lang='de') {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->appendChild(new \DOMElement('dbis_page'));
        $root->setAttributeNode(new \DOMAttr('xsi:noNamespaceSchemaLocation', self::DBIS_PAGE_XSD_URL));
        $root->setAttributeNode(new \DOMAttr('version', '1.0.0'));
        $root->setAttributeNode(new \DOMAttr('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance'));

        if ($organization) {
            $library = $dom->createElement("library", $organization['name']);
        } else {
            $library = $dom->createElement("library", "Gesamtbestand");
        }
        if ($organization) {
            $library->setAttributeNode(new \DOMAttr('bib_id', $organization['dbisId']));
        } else {
            $library->setAttributeNode(new \DOMAttr('bib_id', ''));
        }
        $root->appendChild($library);

        $page_vars = $root->appendChild(new \DOMElement('page_vars'));
        if ($organization) {
            $bib_id = $dom->createElement("bib_id", $organization['dbisId']);
        } else {
            $bib_id = $dom->createElement("bib_id");
        }
        $page_vars->appendChild($bib_id);

        $headline_text = $lang == 'de' ? 'Suche nach Datenbanken (Erweiterte Suche)': 'Search for resources';
        $headline = $dom->createElement("headline", $headline_text);
        $root->appendChild($headline);

        $dbis_search = $root->appendChild(new \DOMElement('dbis_search'));

        {
            $option_list_static = $dbis_search->appendChild(new \DOMElement('option_list'));
            $option_list_static->setAttributeNode(new \DOMAttr('name', 'jq_type'));
            foreach (
                array(
                    "AL" => "Suche über alle Felder",
                    "KT" => "Titelwort(e)",
                    "CO" => "Beschreibung",
                    "KW" => "Schlagwort",
                    "KS" => "Titelanfang",
                    "PU" => "Verlag",
                    "LD" => "Eingabedatum"
                ) as $short_text => $long_text) {
                $option = $dom->createElement("option", $long_text);
                $option->setAttributeNode(new \DOMAttr('value', $short_text));
                $option_list_static->appendChild($option);
            }
        }

        {
            $option_list_static = $dbis_search->appendChild(new \DOMElement('option_list'));
            $option_list_static->setAttributeNode(new \DOMAttr('name', 'jq_bool'));
            foreach (
                array(
                    "AND" => "und",
                    "OR" => "oder"
                ) as $short_text => $long_text) {
                $option = $dom->createElement("option", $long_text);
                $option->setAttributeNode(new \DOMAttr('value', $short_text));
                $option_list_static->appendChild($option);
            }
        }

        {
            $option_list_static = $dbis_search->appendChild(new \DOMElement('option_list'));
            $option_list_static->setAttributeNode(new \DOMAttr('name', 'jq_not'));
            foreach (
                array(
                    "1" => "",
                    "NOT" => "nicht"
                ) as $short_text => $long_text) {
                $option = $dom->createElement("option", $long_text);
                $option->setAttributeNode(new \DOMAttr('value', $short_text));
                $option_list_static->appendChild($option);
            }
        }

        {
            $option_list_subjects = $dbis_search->appendChild(new \DOMElement('option_list'));
            $option_list_subjects->setAttributeNode(new \DOMAttr('name', 'gebiete'));
            foreach ($subjects as $subject) {
                $option = $dom->createElement("option", $subject['title']);
                $option->setAttributeNode(new \DOMAttr('value', Subject::newIdToOldId($subject['id'])));
                $option_list_subjects->appendChild($option);
            }
        }

        {
            $option_list_types = $dbis_search->appendChild(new \DOMElement('option_list'));
            $option_list_types->setAttributeNode(new \DOMAttr('name', 'db_types'));
            /* @var $type Type */
            foreach ($dbTypes as $type) {
                $option = $dom->createElement("option", $type->getTitle()[$lang]);
                $option->setAttributeNode(new \DOMAttr('value', Type::newIdToOldId($type->getId())));
                $option_list_types->appendChild($option);
            }
        }

        {
            $option_list_types = $dbis_search->appendChild(new \DOMElement('option_list'));
            $option_list_types->setAttributeNode(new \DOMAttr('name', 'zugaenge'));
            $option = $dom->createElement("option", "");
            $option->setAttributeNode(new \DOMAttr('value', "1000"));
            $option_list_types->appendChild($option);
            foreach ($licenseTypes as $licenseType) {
                $option = $dom->createElement("option", $licenseType->getTitle()[$lang]);
                $option->setAttributeNode(new \DOMAttr('value', $licenseType->getId()));
                $option_list_types->appendChild($option);
            }
        }

        {
            $option_list_types = $dbis_search->appendChild(new \DOMElement('option_list'));
            $option_list_types->setAttributeNode(new \DOMAttr('name', 'formal_type'));
            $option = $dom->createElement("option", "");
            $option->setAttributeNode(new \DOMAttr('value', "0"));
            $option_list_types->appendChild($option);
            foreach ($publicationForms as $publicationForm) {
                $option = $dom->createElement("option", $publicationForm->getTitle()[$lang]);
                $option->setAttributeNode(new \DOMAttr('value', $publicationForm->getId()));
                $option_list_types->appendChild($option);
            }
        }

        {
            $option_list_countries = $dbis_search->appendChild(new \DOMElement('option_list'));
            $option_list_countries->setAttributeNode(new \DOMAttr('name', 'lcode'));
            /* @var $country Country */
            foreach ($countries as $country) {
                $option = $dom->createElement("option", $country->getTitle()[$lang]);
                $option->setAttributeNode(new \DOMAttr('value', $country->getCode()));
                $option_list_countries->appendChild($option);
            }
        }

        return $dom->saveXML();
    }

    /**
     * @param $search_hits
     * @param Organization|null $organization
     * @param AccessMapping[] $access_mappings
     * @param Subject|null $subject
     * @param string $lang
     * @param $sort_by
     * @param string|null $suchwort
     * @return false|string
     * @throws \DOMException
     */
    public function generateLegacyDbListePage($search_hits, ?Organization $organization, array $access_mappings, ?Subject $subject, ?Collection $collection, string $lang, $sort_by, ?string $suchwort, ?int $total_hits, ?array $db_type_ids, $legacy_query_params=null, $lett=null) {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->appendChild(new \DOMElement('dbis_page'));
        $root->setAttributeNode(new \DOMAttr('xsi:noNamespaceSchemaLocation', self::DBIS_PAGE_XSD_URL));
        $root->setAttributeNode(new \DOMAttr('version', '1.0.0'));
        $root->setAttributeNode(new \DOMAttr('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance'));

        $dbisIdOrEmpty = $organization ? $organization->getDbisId() : '';
        $library = $dom->createElement("library", $organization ? $organization->getName()[$lang] : "Gesamtbestand");
        $root->appendChild($library);
        $library->setAttributeNode(new \DOMAttr('bib_id', $dbisIdOrEmpty));

        $page_vars = $root->appendChild(new \DOMElement('page_vars'));
        $page_vars->appendChild($dom->createElement("bib_id", $dbisIdOrEmpty));
        if ($sort_by) {
            $page_vars->appendChild($dom->createElement("sort", $sort_by));
        }
        $headline_text = 'Ergebnisse Ihrer Suche';
        if ($subject) {
            $page_vars->appendChild($dom->createElement("gebiete", Subject::newIdToOldId($subject->getId())));
            $headline_text = 'Fachgebiet: '.htmlspecialchars($subject->getTitle()[$lang]);
        } else if ($collection) {
            $headline_text = 'Sammlung: '.htmlspecialchars($collection->getTitle()[$lang]);
        }
        if ($db_type_ids && count($db_type_ids)>0) {
            $page_vars->appendChild($dom->createElement("db_type", join(' ', $db_type_ids)));
        }
        $headline = $dom->createElement("headline", $headline_text);
        $root->appendChild($headline);

        if ($suchwort) {
            $page_vars->appendChild($dom->createElement("Suchwort", $suchwort));

            $search_desc = $dom->createElement("search_desc");
            $search_desc_item = $dom->createElement("search_desc_item", 'Suche über alle Felder: "'.$suchwort.'"');
            $search_desc->appendChild($search_desc_item);
            $root->appendChild($search_desc);
        }

        if ($lett){
            $page_vars->appendChild($dom->createElement('lett', $lett));
        }

        if ($legacy_query_params){
            foreach ($legacy_query_params as $param => $param_val){
                $page_vars->appendChild($dom->createElement($param, $param_val));
            }
        }

        $list_dbs = $dom->createElement("list_dbs");
        $root->appendChild($list_dbs);

        $db_access_infos = $dom->createElement("db_access_infos");
        $list_dbs->appendChild($db_access_infos);
        $db_access_infos_created = []; // records which nodes already have been created

        $db_type_infos = $dom->createElement("db_type_infos");
        $list_dbs->appendChild($db_type_infos);
        $db_type_infos_created = []; // records which nodes already have been created

        // This will contain several XML nodes, each named "dbs". Depending on the value of $sort_by,
        // it will either have 'all' and 'top' or it will have one for each db_type_ref
        $dbs = array();

        if ($sort_by != GROUP_BY_TYPE) {
            // we only have TOP DBs at all if a subject was specified
            if ($subject) {
                $dbs['top'] = $dom->createElement("dbs");
                $dbs['top']->setAttributeNode(new \DOMAttr('sort', 'alph'));
                $dbs['top']->setAttributeNode(new \DOMAttr('top_db', '1'));
                $list_dbs->appendChild($dbs['top']);
            }

            $dbs['all'] = $dom->createElement("dbs");
            $dbs['all']->setAttributeNode(new \DOMAttr('sort', 'alph'));
            $list_dbs->appendChild($dbs['all']);
        } else {
            // db_type_ref containers are created later, on demand
        }

        // build a map to lookup resources by their id
        //$resourceid_to_resource = [];
        //foreach ($search_hits as &$resource) {
        //    $resourceid_to_resource[$resource['id']] = $resource;
        //}

        // build a map to lookup access_mappings by their resource_id
        $resourceid_to_accessmapping = [];
        foreach ($access_mappings as &$access_mapping) {
            $resourceid_to_accessmapping[$access_mapping->getResourceId()] = $access_mapping;
        }
        unset($access_mapping);

        // sort order
        if ($subject){
            $this->sortBySubjectSortOrder($search_hits, $subject->getId());
        }

        // iterate over all resources; search within the licenses and accesses
        foreach ($search_hits as &$resource) {
            $resource_id = $resource['resource_id'];
            $is_free = $resource['is_free'] ?? true;
            // these infos can be found somewhere among the accesses
            $href = 'not_found';
            $prefixed_zugang_id = 'not_found';

            // resolve dbis traffic light
            $traffic_light = $this->extractTrafficLight($is_free, $resource['licenses']);

            // also offer the raw license infos so organizations can customize their traffic lights
            $licenseType = null;
            $licenseForm = null;
            $accessType = null;
            $accessForm = null;

            foreach ($resource['licenses'] as $license) {
                $licenseType = $license['type'];
                $licenseForm = $license['form'];
                if (!isset($license['accesses'])) {
                    continue;
                }
                foreach ($license['accesses'] as $access) {
                    //if (!isset($access['label'])) {
                    //    continue; // we need this string for a lookup in the access_mapping table
                    //}

                    $accessType = $access['type'];
                    $accessForm = $access['form'];

                    // no legacy mapping for this access entry available, let's hope the next looks better
                    if (!isset($resourceid_to_accessmapping[$resource_id])) {
                        $href = 'no mapping to old access_id found';
                        $prefixed_zugang_id = 'no mapping to old access_id found';
                    }

                    // save for the generation of the actual "db" node
                    $href = $access['access_url'];
                    $href = UsersWarpTo::buildUrl($href, $resource_id, $access['id'],
                        $organization?->getUbrId(),
                        $license['type'], $license['form'],
                        $access['type'], $access['form']);

                    if (!isset($resourceid_to_accessmapping[$resource_id])) {
                        continue;
                    }

                    $access_mapping = $resourceid_to_accessmapping[$resource_id];
                    $prefixed_zugang_id = $access_mapping->getPrefixedZugangId();

                    // every legacy id gets just one "db_access_info" node
                    if (isset($db_access_infos_created[$prefixed_zugang_id])) {
                        continue;
                    }
                    $db_access_infos_created[$prefixed_zugang_id] = true;

                    // generate the actual "db_access_info" node
                    $db_access_info = $dom->createElement("db_access_info");
                    $db_access_info->setAttributeNode(new \DOMAttr('access_id', $prefixed_zugang_id));
                    //$db_access_info->setAttributeNode(new \DOMAttr('access_url', $access['access_url']));
                    $db_access_info->appendChild(new \DOMElement('db_access', $access_mapping->getKurznutzung()));
                    $db_access_info->appendChild(new \DOMElement('db_access_short_text', $access_mapping->getNutzung()));
                    $db_access_infos->appendChild($db_access_info);
                }
            }

            // scan all dbtypes of this resource for new dbtypes
            $db_type_refs = []; // list of the ids of all dbtypes of this resource
            foreach ($resource['resource_types'] as $resource_type) {
                $db_type_id = $resource_type['id'];
                $db_type_refs[] = (string)$db_type_id; // this means "append" - WTF, PHP...

                // every type id gets just one "db_type_info" node
                if (isset($db_type_infos_created[$db_type_id])) {
                    continue;
                }
                $db_type_infos_created[$db_type_id] = true;

                // generate the actual "db_type_info" node
                $db_type = $resource_type['title'][$lang];
                $db_type_long_text = $resource_type['description'][$lang];
                $db_type_info = $dom->createElement("db_type_info");
                $db_type_info->setAttributeNode(new \DOMAttr('db_type_id', 'db_type_'.$db_type_id));
                $db_type_info->appendChild(new \DOMElement('db_type', $db_type));
                $db_type_info->appendChild(new \DOMElement('db_type_long_text', $db_type_long_text));
                $db_type_infos->appendChild($db_type_info);
            }

            // build the actual "db" node
            $resource_title = htmlspecialchars($resource['resource_title'], ENT_XML1, 'UTF-8');
            $db = $dom->createElement("db", $resource_title);
            $db->setAttributeNode(new \DOMAttr('title_id', $resource['resource_id']));
            if ($prefixed_zugang_id) {
                $db->setAttributeNode(new \DOMAttr('access_ref', $prefixed_zugang_id));
            }
            $db->setAttributeNode(new \DOMAttr('db_type_refs', implode(' ', $db_type_refs)));
            // TODO: remove type/form, leave only traffic_light - talk to torsten witt about this
            if ($licenseType) {
                $db->setAttributeNode(new \DOMAttr('license_type', $licenseType));
            }
            if ($licenseForm) {
                $db->setAttributeNode(new \DOMAttr('license_form', $licenseForm));
            }
            if ($accessType) {
                $db->setAttributeNode(new \DOMAttr('access_type', $accessType));
            }
            if ($accessForm) {
                $db->setAttributeNode(new \DOMAttr('access_form', $accessForm));
            }
            if ($traffic_light) {
                $db->setAttributeNode(new \DOMAttr('traffic_light', $traffic_light));
            }
            if ($href) {
                $db->setAttributeNode(new \DOMAttr('href', $href));
            }

            if ($sort_by != GROUP_BY_TYPE) { // ALPHABETICAL_ or NO_SORTING
                // just add the "db" node to the default container (no copy necessary (yet))
                $dbs['all']->appendChild($db);

                // maybe also add it to top dbs - only show TOP DBs at all if both:
                // - a subject was given
                // - resource is TOP in some subject
                // - resource is TOP DB in the given subject
                if ($subject && $resource['is_top_database']) {
                    // find if the right subject is given
                    $is_top_in_right_subject = false;
                    foreach ($resource['subjects'] as $subjectsOfResource) {
                        if ($subjectsOfResource['id'] == $subject->getId() && $subjectsOfResource['is_top_database']) {
                            $is_top_in_right_subject = true; // resource is TOP in the searched-for subject -> show
                        }
                    }

                    if ($is_top_in_right_subject) {
                        $db_top = $db->cloneNode(deep: true);
                        $db_top->setAttributeNode(new \DOMAttr('top_db', '1'));
                        $dbs['top']->appendChild($db_top);
                    }
                }

            } else { // $sort_by == GROUP_BY_TYPE
                // add copy of $db to each db_type_ref group that it belongs to
                foreach ($db_type_refs as $db_type_id) {
                    $db_type_ref = ''.$db_type_id;

                    // maybe this is the first time we need this container -> create container now
                    if (!array_key_exists($db_type_ref, $dbs)) {
                        $dbs[$db_type_ref] = $dom->createElement("dbs");
                        $dbs[$db_type_ref]->setAttributeNode(new \DOMAttr('sort', 'type'));
                        $dbs[$db_type_ref]->setAttributeNode(new \DOMAttr('db_type_ref', $db_type_ref));
                        $list_dbs->appendChild($dbs[$db_type_ref]);
                    }

                    $dbs[$db_type_ref]->appendChild($db->cloneNode(deep: true));
                }
            }
        }

        // for each container, add an attribute with the total number of entries of that container
        // - except if we use regular search combined with pagination - then use total_hits for the 'all' container
        foreach ($dbs as $key => &$db_container) {
            $db_count = (($key == 'all') && $total_hits) ? $total_hits : $db_container->childElementCount;
            $db_container->setAttributeNode(new \DOMAttr('db_count', $db_count));
        }

        //$search_hits_dump = print_r($search_hits, true);
        //$search_hits_dump = "\n".$search_hits_dump."\n";
        //$debug_node = $root->appendChild($dom->createElement("debug"));
        //$debug_node->appendChild($dom->createCDATASection($search_hits_dump));

        return $dom->saveXML();
    }

    public function generateOrganizations($organizations, $lang='de') {

    }

    public function generateSearchSettings($organization, $lang='de') {

    }

    private function sortBySubjectSortOrder(&$array, $subjectId) {
        usort($array, function ($a, $b) use ($subjectId) {
            // Find the 'sort_order' for the specified subject ID in both arrays
            $sortOrderA = array_column($a['subjects'], 'sort_order', 'id')[$subjectId] ?? PHP_INT_MAX;
            $sortOrderB = array_column($b['subjects'], 'sort_order', 'id')[$subjectId] ?? PHP_INT_MAX;
    
            // Compare based on the 'sort_order' values
            return $sortOrderA <=> $sortOrderB;
        });
    }

}
