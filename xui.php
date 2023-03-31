<?php /** @noinspection ALL */

/*\
 * | - Author : github.com/mobinjavari
 * | - Source : github.com/mobinjavari/v2ray-api-php
 * | - License : github.com/mobinjavari/v2ray-api-php/LICENSE.md
\*/

class xui_fronting_api
{
    private int $port;

    private string $username;

    private string $password;

    private string $default_protocol;

    private string $default_transmission;

    private string $cookies_directory;

    private string $cookie_txt_path;

    public mixed $empty_object;

    public function __construct(
        string $address,
        int $port,
        string $username,
        string $password,
        string $default_protocol = "",
        string $default_transmission = ""
    )
    {
        $this->address = $address;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->default_protocol =
            empty($default_protocol) ? "vless" : $default_protocol;
        $this->default_transmission =
            empty($default_transmission) ? "ws" : $default_transmission;
        $this->empty_object = new stdClass();
        $this->cookies_directory = "./.cookies/";
        $this->cookie_txt_path = "$this->cookies_directory$this->address.$this->port.txt";

        if(!is_dir($this->cookies_directory)) mkdir($this->cookies_directory);

        if(!file_exists($this->cookie_txt_path))
        {
            $login = $this->login();

            if(!$login["success"])
            {
                unlink($this->cookie_txt_path);
                exit($login["msg"]);
            }
        }

        if(count($this->list()) < 5)
        {
            $create = 5 - count($this->list());

            while ($create)
            {
                $this->add();
                $create--;
            }
        }
    }

    public function request(string $method, array | string $param = "") : array
    {
        $URL = "$this->address:$this->port/$method";
        $POST = is_array($param) ? json_encode($param) : $param;
        $options = [
            CURLOPT_URL => $URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_COOKIEFILE => $this->cookie_txt_path,
            CURLOPT_COOKIEJAR => $this->cookie_txt_path,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $POST
        ];

        if(is_array($param)) $options[CURLOPT_HTTPHEADER] = ["Content-Type: application/json"];

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return match ($http_code) {
            200 => json_decode($response,true),
            default => "Error $http_code"
        };
    }

    public function login() : array
    {
        return $this->request("login",[
            "username" => $this->username,
            "password" => $this->password
        ]);
    }

    public function list(array $filter = []) : array
    {
        $list = $this->request(
            "xui/inbound/list"
        )["obj"];

        if(!empty($filter))
        {
            $result = [];

            for ($list_andis = 0, $num = 0; $num < count($list); $num++)
            {
                $email = empty($filter["email"]) ? "" : $filter["email"];
                $client_stats = $list[$num]["clientStats"];
                $settings = json_decode($list[$num]["settings"],true);
                $remark = $list[$num]["remark"];
                $port = (int)$list[$num]["port"];
                $protocol = $list[$num]["protocol"];
                $stream_settings = json_decode($list[$num]["streamSettings"],true);

                if(!empty($email))
                {
                    for ($num_2 = 0; $num_2 < count($settings["clients"]); $num_2++)
                    {
                        if($settings["clients"][$num_2]["email"] == $filter["email"])
                        {
                            $result[$list_andis]["protocol"] = $protocol;
                            $result[$list_andis]["network"] = $stream_settings["network"];
                            $result[$list_andis]["uid"] = $settings["clients"][$num_2]["id"];
                            $result[$list_andis]["flow"] = $settings["clients"][$num_2]["flow"];
                            $result[$list_andis]["email"] = $settings["clients"][$num_2]["email"];
                            $result[$list_andis]["limitIp"] = (int)$settings["clients"][$num_2]["limitIp"];
                            $result[$list_andis]["totalGB"] = (int)$settings["clients"][$num_2]["totalGB"];
                            $result[$list_andis]["fingerprint"] = $settings["clients"][$num_2]["fingerprint"];
                            $result[$list_andis]["expiryTime"] = (int)$settings["clients"][$num_2]["expiryTime"];
                            $result[$list_andis]["clientAndis"] = $num_2;
                            break;
                        }
                    }
                    if(!empty($result))
                    {
                        for ($num_2 = 0; $num_2 < count($client_stats); $num_2++)
                        {
                            if($client_stats[$num_2]["email"] == $filter["email"])
                            {
                                $result[$list_andis]["id"] = (int)$client_stats[$num_2]["id"];
                                $result[$list_andis]["inboundId"] = (int)$client_stats[$num_2]["inboundId"];
                                $result[$list_andis]["enable"] = (int)$client_stats[$num_2]["enable"];
                                $result[$list_andis]["up"] = (int)$client_stats[$num_2]["up"];
                                $result[$list_andis]["down"] = (int)$client_stats[$num_2]["down"];
                                $result[$list_andis]["expiryTime"] = (int)$client_stats[$num_2]["expiryTime"];
                                $result[$list_andis]["total"] = (int)$client_stats[$num_2]["total"];
                                $result[$list_andis]["statsAndis"] = $num_2;
                                $result[$list_andis]["url"] = $this->url(
                                    $protocol,
                                    $result[$list_andis]["uid"],
                                    "$remark-$email",
                                    $stream_settings["network"],
                                    $port,
                                    $result[$list_andis]["fingerprint"],
                                    $stream_settings["wsSettings"]["path"]
                                );
                                $list_andis++;
                                break;
                            }
                        }
                    }
                }
            }

            if(count($result) == 1) return $result[0];
            elseif(count($result) == 0) return [];
            else return $result;
        }

        return $list;
    }

    public function url(
        string $porotocol,
        string $uid,
        string $remark,
        string $transmission,
        int $port,
        string $fp,
        string $path
    ) : string
    {
        switch ($porotocol)
        {
            case "vmess":
                $vmess_url = "vmess://";
                $path = $transmission == "ws" ? "/" : "";
                $vmess_settings = [
                    "v" => "2",
                    "ps" => $remark,
                    "add" => $this->address,
                    "port" => $port,
                    "id" => $uid,
                    "aid" => 0,
                    "net" => $transmission,
                    "type" => "none",
                    "host" => "",
                    "path" => $path,
                    "tls" => "tls"
                ];
                $vmess_base = base64_encode(json_encode($vmess_settings));
                return $vmess_url.$vmess_base;

            case "vless":
                $vless_url = "vless://$uid";
                $vless_url .= "@$this->address:$port";
                $vless_url .= "?type=$transmission&security=tls&path=$path&fp=$fp&encryption=none";
                $vless_url .= "#$remark";
                return $vless_url;

            default:return "Error, url could not be created";
        }
    }

    /**
     * @throws Exception
     */
    private function guidv4()
    {
        $data = random_bytes(16);
        assert(strlen($data) == 16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    function update(int $andis, int $client_andis, array $changes) : bool
    {
        if(!empty($changes))
        {
            $list = $this->list();

            if(isset($changes["settings"]))
            {
                $settings = json_decode($list[$andis]["settings"],true);

                if(!empty($changes["settings"]["clients"]["limitIp"]))
                    $settings["clients"][$client_andis]["limitIp"] =
                        $changes["settings"]["clients"]["limitIp"];

                if(!empty($changes["settings"]["clients"]["totalGB"]))
                    $settings["clients"][$client_andis]["totalGB"] =
                        $changes["settings"]["clients"]["totalGB"];

                if(!empty($changes["settings"]["clients"]["expiryTime"]))
                    $settings["clients"][$client_andis]["expiryTime"] =
                        $changes["settings"]["clients"]["expiryTime"];

                $settings = json_encode($settings);
            } else $settings = "";

            $result["up"] = isset($changes["up"]) ? $changes["up"] : $list[$andis]["up"];
            $result["down"] = isset($changes["down"]) ? $changes["down"] : $list[$andis]["down"];
            $result["total"] = isset($changes["total"]) ? $changes["total"] : $list[$andis]["total"];
            $result["remark"] = isset($changes["remark"]) ? $changes["remark"] : $list[$andis]["remark"];
            $result["enable"] = isset($changes["enable"]) ? $changes["enable"] : $list[$andis]["enable"];
            $result["expiryTime"] = isset($changes["expiryTime"]) ? $changes["expiryTime"] : $list[$andis]["expiryTime"];
            $result["clientStats"] = null;
            $result["listen"] = $list[$andis]["listen"];
            $result["port"] = $list[$andis]["port"];
            $result["protocol"] = $list[$andis]["protocol"];
            $result["settings"] = empty($settings) ? $list[$andis]["settings"] : $settings;
            $result["streamSettings"] = $list[$andis]["streamSettings"];
            $result["tag"] = $list[$andis]["tag"];
            $result["sniffing"] = $list[$andis]["sniffing"];

            $send = $this->request("xui/inbound/update/".++$andis,$result);

            return (bool)$send["success"];
        }

        return true;
    }

    public function new(string $email, int $total, int $ex)
    {
        $total = $total * 1024 * 1024 * 1024;

        for ($andis = 0; $andis < 5; $andis++)
        {
            $json_settings = json_decode($this->list()[$andis]["settings"],true);
            $count_settings = count($json_settings["clients"]);

            if($count_settings <= 20) {
                $json_settings["clients"][$count_settings] = [
                    "id" => $this->guidv4(),
                    "flow" => "",
                    "email" => $email,
                    "limitIp" => 0,
                    "totalGB" => $total,
                    "expiryTime" => $ex
                ];
                $send["settings"] = json_encode($json_settings);
                $send["id"] = ++$andis;
                $result = $this->request("xui/inbound/addClient",$send);
                return $result["success"];
            }
        }

        return false;
    }

    public function add(
        string $remark = "",
        string $protocol = "vless",
        int $port = 0,
        int $expire_time = 0
    ) : bool
    {
        $ports = [2053,2083,2087,2096,8443];
        $inbound_count = count($this->list());
        $port = $port == 0 ? $ports[$inbound_count] : $port;
        $remark = empty($remark) ? "$port" : $remark;
        $settings = [
            "clients" => [
                [
                    "id" => $this->guidv4(),
                    "flow" => "",
                    "email" => "Ping-Test-".rand(1111,9999),
                    "limitIp" => 0,
                    "totalGB" => 0,
                    "fingerprint" => "chrome",
                    "expiryTime" => ""
                ]
            ],
            "decryption" => "none",
            "fallbacks" => []
        ];
        $stream_settings = [
            "network" => "ws",
            "security" => "tls",
            "tlsSettings" => [
                "serverName" => "",
                "minVersion" => "1.2",
                "maxVersion" => "1.3",
                "cipherSuites" => "",
                "certificates" => [
                    [
                        "certificateFile" => "/root/cert.crt",
                        "keyFile" => "/root/private.key"
                    ]
                ],
                "alpn" => ["h2", "http/1.1"],
            ],
            "wsSettings" => [
                "acceptProxyProtocol" => false,
                "path" => "/",
                "headers" => $this->empty_object
            ]
        ];
        $sniffing = [
            "enabled" => true,
            "destOverride" => ["http","tls"]
        ];
        $add = [
            "up" => 0,
            "down" => 0,
            "total" => 0,
            "remark" => "$remark",
            "enable" => true,
            "expireTime" => $expire_time,
            "clientStats" => null,
            "listen" => "",
            "port" => $port,
            "protocol" => $protocol,
            "settings" => json_encode($settings),
            "streamSettings" => json_encode($stream_settings),
            "sniffing" => json_encode($sniffing)
        ];

        return (bool)$this->request(
            "xui/inbound/add",$add
        )["success"];
    }

    public function reset($client) : bool
    {
        return (bool)$this->request(
            "xui/inbound/resetClientTraffic/$client"
        )["success"];
    }

    public function del($id) : bool
    {
        return (bool)$this->request(
            "xui/inbound/del/$id"
        )["success"];
    }

    public function status() : array
    {
        $status = $this->request(
            "server/status"
        )["obj"];

        $status["cpu"] = round($status["cpu"]) ."%";
        $status["mem"]["current"] = $this->formatBytes($status["mem"]["current"]);
        $status["mem"]["total"] = $this->formatBytes($status["mem"]["total"]);
        $status["swap"]["current"] = $this->formatBytes($status["swap"]["current"]);
        $status["swap"]["total"] = $this->formatBytes($status["swap"]["total"]);
        $status["disk"]["current"] = $this->formatBytes($status["disk"]["current"]);
        $status["disk"]["total"] = $this->formatBytes($status["disk"]["total"]);
        $status["netIO"]["up"] = $this->formatBytes($status["netIO"]["up"]);
        $status["netIO"]["down"] = $this->formatBytes($status["netIO"]["down"]);
        $status["netTraffic"]["sent"] = $this->formatBytes($status["netTraffic"]["sent"]);
        $status["netTraffic"]["recv"] = $this->formatBytes($status["netTraffic"]["recv"]);
        $status["uptime"] = $this->formatTime($status["uptime"]);

        return $status;
    }

    public function formatBytes(int $size,int $format = 2, int $precision = 2) : string
    {
        $base = log($size, 1024);

        if($format == 1) {
            $suffixes = ['بایت', 'کلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت']; # Persian
        } elseif ($format == 2) {
            $suffixes = ["B", "KB", "MB", "GB", "TB"];
        } else {
            $suffixes = ['B', 'K', 'M', 'G', 'T'];
        }

        if($size <= 0) return "0 ".$suffixes[1];

        $result = pow(1024, $base - floor($base));
        $result = round($result, $precision);
        $suffixes = $suffixes[floor($base)];

        return $result ." ". $suffixes;
    }

    public function formatTime(int $time, int $format = 2) : string
    {
        if($format == 1) {
            $lang = ["ثانیه","دقیقه","ساعت","روز","هفته","ماه","سال"]; # Persian
        } else {
            $lang = ["Second(s)","Minute(s)","Hour(s)","Day(s)","Week(s)","Month(s)","Year(s)"];
        }

        if($time >= 1 && $time < 60) {
            return round($time) . " " . $lang[0];
        } elseif ($time >= 60 && $time < 3600) {
            return round($time / 60) . " " . $lang[1];
        } elseif ($time >= 3600 && $time < 86400) {
            return round($time / 3600) . " " . $lang[2];
        } elseif ($time >= 86400 && $time < 604800) {
            return round($time / 86400) . " " . $lang[3];
        } elseif ($time >= 604800 && $time < 2600640) {
            return round($time / 604800) . " " . $lang[4];
        } elseif ($time >= 2600640 && $time < 31207680) {
            return round($time / 2600640) . " " . $lang[5];
        } elseif ($time >= 31207680) {
            return round($time / 31207680) . " " . $lang[6];
        } else {
            return "Not supported";
        }
    }
}
