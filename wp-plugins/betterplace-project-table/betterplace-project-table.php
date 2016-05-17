<?php
/*
  Plugin Name: Betterplace Projects Table
  Plugin URI: https://github.com/freifunk/www.freifunk.net
  Description: creates a table of given betterplace and boost donation projects
  Version: 1.4.0
  Author: Andreas Bräu
  Author URI: http://blog.andi95.de
  License: GPLv2 or later
  License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

include_once("bpt/DonationFactory.php");
include_once("bpt/class.ffapi.php");
include_once("bpt/class.bpproject.php");
include_once("bpt/class.boostCampaign.php");

function betterplaceprojecttable($atts)
{
    $use_ffapi = null;
    $a = shortcode_atts(array(
        'orderBy' => 'openAmount',
        'sort' => 'desc',
        'use_ffapi' => 'true',
        'show_provider' => 'true',
        'more_campaigns' => null
    ), $atts);

    $orderBy = $a['orderBy'];
    $sort = $a['sort'];
    $use_ffapi = $a['use_ffapi'];
    $show_provider = $a['show_provider'];
    $more_campaigns = $a['more_campaigns'];

    $campaigns = array();

    if ($use_ffapi === 'true') {
        $ffapi = new ffapi(get_option('ffapi_summarized_dir'));
        $campaigns = $ffapi->getValues("support.donations.campaigns");
    }
    $df = new DonationFactory();
    $bpProjects = array();
    $output = "";

    if (!empty($more_campaigns)) {
        $additionalCampaigns = explode(",", $more_campaigns);
        foreach ($additionalCampaigns as $number => $ac) {
            array_push($campaigns, array("projectid" => $ac, "provider" => "betterplace"));
        }
    }

    $campaigns = array_unique($campaigns, SORT_REGULAR);

    foreach ($campaigns as $name => $project) {
        if (false === ($bp = get_transient($project['provider'] . $project['projectid']))) {
            $bp = $df->getDonationClass($project['provider'], $project['projectid'], $name);
            set_transient($project['provider'] . $project['projectid'], $bp, get_option('cache_timeout'));
        }
        if (is_object($bp)) {
            array_push($bpProjects, $bp->getProjectArray());
        }
    }

    usort($bpProjects, function ($a, $b) use ($orderBy) {
        return $a[$orderBy] - $b[$orderBy];
    });

    if (!empty($sort) && $sort == "desc") {
        $bpProjects = array_reverse($bpProjects);
    }
    wp_enqueue_script('sortable', get_template_directory_uri() . '/js/sorttable.js', array(), null, false);

    $output .= "<div class=\"betterplace-table\">";
    $output .= "<table class=\"sortable betterplace-table\">";
    $output .= "<thead>";
    $output .= "<th class=\"" . getSortedClass($orderBy, "projectTitle") . "\">Projekt";
    if ($show_provider === 'true') $output .= "/Träger";
    $output .= getSortSign($orderBy, "projectTitle") . "</th>";
    $output .= "<th class=\"sorttable_numeric " . getSortedClass($orderBy, "incompleteNeed") . "\">" . __('Bedarfe', 'bpt') . getSortSign($orderBy, "incompleteNeed") . " </th>";
    $output .= "<th class=\"sorttable_numeric " . getSortedClass($orderBy, "completedNeed") . "\">Erfüllt" . getSortSign($orderBy, "completedNeed") . " </th>";
    $output .= "<th class=\"sorttable_numeric " . getSortedClass($orderBy, "donors") . "\">Spender" . getSortSign($orderBy, "donors") . " </th>";
    $output .= "<th class=\"sorttable_numeric " . getSortedClass($orderBy, "progress") . "\">Fortschritt" . getSortSign($orderBy, "progress") . " </th>";
    $output .= "<th class=\"sorttable_numeric " . getSortedClass($orderBy, "openAmount") . "\">Spenden" . getSortSign($orderBy, "openAmount") . " </th>";
    $output .= "</thead>";

    foreach ($bpProjects as $bpProject) {
        $output .= "<tr>";
        $output .= "<td class=\"organization\">" . $bpProject['projectTitle'];
        if ($show_provider === 'true' && !empty($bpProject['organization'])) $output .= "<br/><a href=\"#" . $bpProject['organization'] . "\">" . $bpProject['organization'] . "</a>";
        $output .= "</td>";
        $output .= "<td class=\"numeric\">";
        if (!empty($bpProject['incompleteNeed'])) $output .= $bpProject['incompleteNeed'];
        $output .= "</td>";
        $output .= "<td class=\"numeric\">" . $bpProject['completedNeed'] . "</td>";
        $output .= "<td class=\"numeric\">" . $bpProject['donors'] . "</td>";
        if (empty($bpProject['progress'])) {
            $output .= "<td></td>";
        } else {
            $output .= "<td class=\"progress\" sorttable_customkey='" . $bpProject['progress'] . "'>" . do_shortcode("[wppb progress=" . $bpProject['progress'] . " fullwidth=false option=flat location=inside color=#009ee0]") . "</td>";
        }
        if (empty($bpProject['openAmount']) && !empty($bpProject['totalAmount'])) {
            $output .= "<td class=\"donor\" sorttable_customkey='0'>Schon " . round($bpProject['totalAmount'] / 100) . " € sind da<a href=\"" . $bpProject['projectLink'] . "\" target=\"_blank\"><button>Jetzt spenden!</button></a></td>";
        } else {
            $output .= "<td class=\"donor\" sorttable_customkey='" . $bpProject['openAmount'] . "'>Es fehlen noch " . round($bpProject['openAmount'] / 100) . " €<a href=\"" . $bpProject['projectLink'] . "\" target=\"_blank\"><button>Jetzt spenden!</button></a></td>";
        }
        $output .= "</tr>";
    }

    $output .= "</table>";
    $output .= "</div>";
    return $output;
}

function getSortSign($orderBy, $column)
{
    if ($orderBy == $column) {
        return "<span id='sorttable_sortfwdind'>&nbsp;▾</span>";
    } else {
        return "";
    }
}

function getSortedClass($orderBy, $column)
{
    if ($orderBy == $column) {
        return "sorttable_sorted";
    } else {
        return "";
    }
}


add_option('ffapi_summarized_dir', "https://api.freifunk.net/map/ffSummarizedDir.json");
add_option('http_timeout', 2);
add_option('cache_timeout', 1 * HOUR_IN_SECONDS);

add_shortcode("bpprojecttable", "betterplaceprojecttable");
?>
