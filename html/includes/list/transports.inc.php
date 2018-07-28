<?php
/**
 * transports.inc.php
 *
 * List transports
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2018 Vivia Nguyen-Tran
 * @author     Vivia Nguyen-Tran <vivia@ualberta>
 */

use LibreNMS\Authentication\Auth;

if (!Auth::user()->hasGlobalRead()) {
    return [];
}

$query = '';
$params = [];

if (!empty($_REQUEST['search'])) {
    $query .= ' WHERE `transport_name` LIKE ?';
    $params[] = '%' . mres($_REQUEST['search']) . '%';
}

$total = dbFetchCell("SELECT COUNT(*) FROM `alert_transports` $query", $params);
$more = false;

if (!empty($_REQUEST['limit'])) {
    $limit = (int) $_REQUEST['limit'];
    $page = isset($_REQUEST['page']) ? (int) $_REQUEST['page'] : 1;
    $offset = ($page - 1) * $limit;

    $query .= " LIMIT $offset, $limit";
} else {
    $offset = 0;
}

$sql = "SELECT `transport_id` AS `id`, `transport_name` AS `text`, `transport_type` AS `type` FROM `alert_transports` $query";
$transports = dbFetchRows($sql, $params);

$more = ($offset + count($transports))<$total;
$transports = array_map(function ($transport) {
    $transport['text'] = ucfirst($transport['type']).": ".$transport['text'];
    unset($transport['type']);
    return $transport;
}, $transports);

$data = [['text' => 'Transports', 'children' => $transports]];
return[$data, $more];