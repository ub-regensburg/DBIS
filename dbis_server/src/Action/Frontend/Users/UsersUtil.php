<?php

namespace App\Action\Frontend\Users;

/**
 * UsersUtil
 *
 * Utility functions for user pages.
 *
 */
class UsersUtil
{
    /**
     * Sort assocs of DBIS resources according to propoer presentation logic:
     * - top-resources are presented before non-top-resources
     * - top-resources with higher ranking are presented before top-resources
     *  with lower rankings
     * - sort order of database engine is retained
     * @param array $resources
     * @return array
     */
    public static function sortResourceAssocs(array $resources): array
    {
        $top_resources = array_filter($resources, function ($resource) {
            return array_key_exists("is_top", $resource) and $resource["is_top"];
        });

        $resources_without_top_resources = array_filter($resources, function ($resource) {
            return !array_key_exists("is_top", $resource) || !$resource["is_top"];
            // When searching is_top does not exist; in subject view is_top exists but is empty
        });

        usort($top_resources, function ($a, $b) {
            return $a['top_order'] <= $b['top_order'] ? -1 : 1;
        });

        return array_merge($top_resources, $resources_without_top_resources);
    }
}
