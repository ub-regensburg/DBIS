<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Domain\Resources\Entities\LicenseLocalisation;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class UpdateLicenseLocalisationAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $params = $request->getParsedBody();

        $ubrId = $params['org'];
        $license_id = (int) $params['license_id'];
        $internal_notes_for_org_de = $params['internal_notes_for_org_de'] && strlen($params['internal_notes_for_org_de']) > 0 ? $params['internal_notes_for_org_de'] : null;
        $internal_notes_for_org_en = $params['internal_notes_for_org_en'] && strlen($params['internal_notes_for_org_en']) > 0 ? $params['internal_notes_for_org_en'] : null;
        $external_notes_for_org_de = $params['external_notes_for_org_de'] && strlen($params['external_notes_for_org_de']) > 0 ? $params['external_notes_for_org_de'] : null;
        $external_notes_for_org_en = $params['external_notes_for_org_en'] && strlen($params['external_notes_for_org_en']) > 0 ? $params['external_notes_for_org_en'] : null;

        $aquired_by_organisation = $params['aquired_by_organisation'] && strlen($params['aquired_by_organisation']) > 0 ? $params['aquired_by_organisation'] : null;
        $cancelled_by_organisation = $params['cancelled_by_organisation'] && strlen($params['cancelled_by_organisation']) > 0 ? $params['cancelled_by_organisation'] : null;
        $last_check_by_organisation = $params['last_check_by_organisation'] && strlen($params['last_check_by_organisation']) > 0 ? $params['last_check_by_organisation'] : null;

        $internal_notes = null;
        if($internal_notes_for_org_de || $internal_notes_for_org_en) {
            if ($internal_notes_for_org_de && $internal_notes_for_org_en) {
                $internal_notes = array('de' => $internal_notes_for_org_de , 'en' => $internal_notes_for_org_en);
            } else {
                if ($internal_notes_for_org_de) {
                    $internal_notes = array('de' => $internal_notes_for_org_de , 'en' => "");
                } else {
                    $internal_notes = array('de' => "" , 'en' => $internal_notes_for_org_en);
                }
            }
        }
        
        $external_notes = null;
        if($external_notes_for_org_de || $external_notes_for_org_en) {
            if ($external_notes_for_org_de && $external_notes_for_org_en) {
                $external_notes = array('de' => $external_notes_for_org_de , 'en' => $external_notes_for_org_en);
            } else {
                if ($external_notes_for_org_de) {
                    $external_notes = array('de' => $external_notes_for_org_de , 'en' => "");
                } else {
                    $external_notes = array('de' => "" , 'en' => $external_notes_for_org_en);
                }
            }
        }

        $licenseLocalsiation = new LicenseLocalisation($ubrId, $license_id);
        $licenseLocalsiation->setExternalNotes($external_notes);
        $licenseLocalsiation->setInternalNotes($internal_notes);
        $licenseLocalsiation->setAquired($aquired_by_organisation);
        $licenseLocalsiation->setCancelled($cancelled_by_organisation);
        $licenseLocalsiation->setLastCheck($last_check_by_organisation);

        $this->service->updateLicenseLocalisation($licenseLocalsiation);

        // TODO: Return errors if they occur
        $data = array(
            'errors' => array()
        );

        $data = json_encode($data);

        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
