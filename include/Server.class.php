<?php
/**
 * copyright 2013      Glenn De Jonghe
 *           2014-2015 Daniel Butum <danibutum at gmail dot com>
 * This file is part of SuperTuxKart
 *
 * stk-addons is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * stk-addons is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with stk-addons. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Server class
 */
class Server implements IAsXML
{
    /**
     * The server id
     * @var int
     */
    private $id;

    /**
     * The user who created the server
     * @var int
     */
    private $host_id;

    /**
     * The server name
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $max_players;

    /**
     * The server's IP address
     * @var int
     */
    private $ip;

    /**
     * The server's public port
     * @var int
     */
    private $port;

    /**
     * The server's private port
     * @var int
     */
    private $private_port;

    /**
     * The server's difficulty
     * @var int
     */
    private $difficulty;

    /**
     * The server's game mode
     * @var int
     */
    private $game_mode;

    /**
     * @var int
     */
    private $current_players;

    /**
     * @var int
     */
    private $password;

    /**
     * @var int
     */
    private $version;

    /**
     * @var array
     * Latitude and longitude in float of server itself
     */
    private $coordinates;

    /**
     * @var array
     * Latitude and longitude in float of client which is getting the list of servers.
     */
    private $client_coordinates;

    /**
     *
     * @param array $data an associative array retrieved from the database
     */
    private function __construct(array $data)
    {
        $this->id = (int)$data["id"];
        $this->host_id = (int)$data["host_id"];
        $this->name = $data["name"];
        $this->max_players = (int)$data["max_players"];
        $this->ip = $data["ip"];
        $this->port = (int)$data["port"];
        $this->private_port = (int)$data["private_port"];
        $this->difficulty = (int)$data["difficulty"];
        $this->game_mode = (int)$data["game_mode"];
        $this->current_players = (int)$data["current_players"];
        $this->password = (int)$data["password"];
        $this->version = (int)$data["version"];
        $this->coordinates = array($data["latitude"], $data["longitude"]);
        // Set by each get-all later, this is a placeholder for the validation of server creation
        $this->client_coordinates = array(1000.0, 1000.0);
    }

    /**
     * @return int
     */
    public function getServerId()
    {
        return $this->id;
    }

    public function setClientCoordinates($coordinates)
    {
        $this->client_coordinates = $coordinates;
    }

    /**
     * @return int
     */
    public function getHostId()
    {
        return $this->host_id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getMaxPlayers()
    {
        return $this->max_players;
    }

    /**
     * Get the latitude and longitude of an IP
     * @return array of latitude and longitude in float
     */
    public static function getIPCoordinates($ip)
    {
        try
        {
            $result = DBConnection::get()->query(
                "SELECT * FROM `" . DB_PREFIX . "ip_mapping`
                WHERE `ipstart` <= :ip AND `ipend` >= :ip ORDER BY `ipstart` DESC LIMIT 1;",
                DBConnection::FETCH_FIRST,
                [':ip' => $ip],
                [":ip" => DBConnection::PARAM_INT]
            );
        }
        catch (DBException $e)
        {
            throw new ServerException(exception_message_db(_('finding ip coordinates')));
        }

        if (!$result)
        {
            return array(1000.0, 1000.0);
        }
        return array($result["latitude"], $result["longitude"]);
    }

    /**
     * Get distance in miles between two coordinates using Haversine formula
     * if any coordinates is not between -+90 (latitude) or -+ 180 (longitude) return -1.0
     * @return float
     */
    public static function getDistance(array $c1, array $c2)
    {
        if (abs($c1[0]) > 90.0 || abs($c2[0]) > 90.0 ||
            abs($c1[1]) > 180.0 || abs($c2[1]) > 180.0)
            return -1.0;
        $earth_radius = 3958.755;
        $lat_from = deg2rad($c1[0]);
        $lon_from = deg2rad($c1[1]);
        $lat_to = deg2rad($c2[0]);
        $lon_to = deg2rad($c2[1]);
        $lat_delta = $lat_to - $lat_from;
        $lon_delta = $lon_to - $lon_from;
        $angle = 2 * asin(sqrt(pow(sin($lat_delta / 2), 2) +
            cos($lat_from) * cos($lat_to) * pow(sin($lon_delta / 2), 2)));
        return $angle * $earth_radius;
    }

    /**
     * Get server as xml output
     *
     * @return string
     */
    public function asXML()
    {
        $server_xml = new XMLOutput();
        $server_xml->startElement('server');
        $server_xml->writeAttribute("id", $this->id);
        $server_xml->writeAttribute("host_id", $this->host_id);
        $server_xml->writeAttribute("name", $this->name);
        $server_xml->writeAttribute("max_players", $this->max_players);
        $server_xml->writeAttribute("ip", $this->ip);
        $server_xml->writeAttribute("port", $this->port);
        $server_xml->writeAttribute("private_port", $this->private_port);
        $server_xml->writeAttribute("difficulty", $this->difficulty);
        $server_xml->writeAttribute("game_mode", $this->game_mode);
        $server_xml->writeAttribute("current_players", $this->current_players);
        $server_xml->writeAttribute("password", $this->password);
        $server_xml->writeAttribute("version", $this->version);
        $server_xml->writeAttribute("distance",
            Server::getDistance($this->client_coordinates, $this->coordinates));
        $server_xml->endElement();

        return $server_xml->asString();
    }

    /**
     * Create server
     *
     * @param string $ip
     * @param int    $port
     * @param int    $private_port
     * @param int    $user_id
     * @param string $server_name
     * @param int    $max_players
     * @param int    $difficulty
     * @param int    $game_mode
     * @param int    $password
     * @param int    $version
     *
     * @return Server
     * @throws ServerException
     */
    public static function create($ip, $port, $private_port, $user_id,
        $server_name, $max_players, $difficulty, $game_mode, $password, $version)
    {
        try
        {
            // Clean non-polled servers < 15 seconds before
            $timeout = time() - 15;
            DBConnection::get()->query(
                "DELETE FROM `" . DB_PREFIX . "servers`
                WHERE `last_poll_time` < :time",
                DBConnection::NOTHING,
                [ ':time'   => $timeout ],
                [ ':time'   => DBConnection::PARAM_INT]
            );

            $count = DBConnection::get()->query(
                "SELECT `id` FROM `" . DB_PREFIX . "servers` WHERE `ip`= :ip AND `port`= :port ",
                DBConnection::ROW_COUNT,
                [':ip' => $ip, ':port' => $port]
            );
            if ($count)
            {
                throw new ServerException(_('Specified server already exists.'));
            }

            $server_coordinates = Server::getIPCoordinates($ip);
            $result = DBConnection::get()->query(
                "INSERT INTO `" . DB_PREFIX . "servers` (host_id, name,
                last_poll_time, ip, port, private_port, max_players,
                difficulty, game_mode, password, version, latitude, longitude)
                VALUES (:host_id, :name, :last_poll_time, :ip, :port,
                :private_port, :max_players, :difficulty, :game_mode,
                :password, :version, :latitude, :longitude)",
                DBConnection::ROW_COUNT,
                [
                    ':host_id'        => $user_id,
                    ':name'           => $server_name,
                    ':last_poll_time' => time(),
                    // Do not use (int) or it truncates to 127.255.255.255
                    ':ip'             => $ip,
                    ':port'           => $port,
                    ':private_port'   => $private_port,
                    ':max_players'    => $max_players,
                    ':difficulty'     => $difficulty,
                    ':game_mode'      => $game_mode,
                    ':password'       => $password,
                    ':version'        => $version,
                    ':latitude'       => strval($server_coordinates[0]),
                    ':longitude'      => strval($server_coordinates[1])
                ],
                [
                    ':host_id'        => DBConnection::PARAM_INT,
                    ':name'           => DBConnection::PARAM_STR,
                    ':last_poll_time' => DBConnection::PARAM_INT,
                    ':ip'             => DBConnection::PARAM_INT,
                    ':port'           => DBConnection::PARAM_INT,
                    ':private_port'   => DBConnection::PARAM_INT,
                    ':max_players'    => DBConnection::PARAM_INT,
                    ':difficulty'     => DBConnection::PARAM_INT,
                    ':game_mode'      => DBConnection::PARAM_INT,
                    ':password'       => DBConnection::PARAM_INT,
                    ':version'        => DBConnection::PARAM_INT,
                    ':latitude'       => DBConnection::PARAM_STR,
                    ':longitude'      => DBConnection::PARAM_STR
                ]
            );
        }

        catch (DBException $e)
        {
            throw new ServerException(exception_message_db(_('create a server')));
        }

        if ($result != 1)
        {
            throw new ServerException(_h('Could not create server'));
        }

        return Server::getServer(DBConnection::get()->lastInsertId());
    }

    /**
     * Get a server instance by id
     *
     * @param int $id
     *
     * @return Server
     * @throws ServerException
     */
    public static function getServer($id)
    {
        try
        {
            $result = DBConnection::get()->query(
                "SELECT * FROM `" . DB_PREFIX . "servers`
                WHERE `id`= :id",
                DBConnection::FETCH_ALL,
                [':id' => $id],
                [":id" => DBConnection::PARAM_INT]
            );
        }
        catch (DBException $e)
        {
            throw new ServerException(exception_message_db(_('retrieve a server')));
        }

        if (!$result)
        {
            throw new ServerException(_h("Server doesn't exist."));
        }
        else if (count($result) > 1)
        {
            throw new ServerException("Multiple servers match the same id.");
        }

        return new self($result[0]);
    }

    /**
     * Get all servers as xml output
     *
     * @return string
     */
    public static function getServersAsXML()
    {
        $servers = DBConnection::get()->query(
            "SELECT *
            FROM `" . DB_PREFIX . "servers`",
            DBConnection::FETCH_ALL
        );

        // build xml
        $partial_output = new XMLOutput();
        $partial_output->startElement('servers');
        $client_ip = ip2long($_SERVER['REMOTE_ADDR']);
        $client_coordinates = Server::getIPCoordinates($client_ip);
        foreach ($servers as $server_result)
        {
            $server = new self($server_result);
            $server->setClientCoordinates($client_coordinates);
            $partial_output->insert($server->asXML());
        }
        $partial_output->endElement();

        return $partial_output->asString();
    }
}
