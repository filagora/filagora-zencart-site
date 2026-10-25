<?php

namespace Hp;

//  PROJECT HONEY POT ADDRESS DISTRIBUTION SCRIPT
//  For more information visit: http://www.projecthoneypot.org/
//  Copyright (C) 2004-2026, Unspam Technologies, Inc.
//
//  This program is free software; you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation; either version 2 of the License, or
//  (at your option) any later version.
//
//  This program is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with this program; if not, write to the Free Software
//  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA
//  02111-1307  USA
//
//  If you choose to modify or redistribute the software, you must
//  completely disconnect it from the Project Honey Pot Service, as
//  specified under the Terms of Service Use. These terms are available
//  here:
//
//  http://www.projecthoneypot.org/terms_of_service_use.php
//
//  The required modification to disconnect the software from the
//  Project Honey Pot Service is explained in the comments below. To find the
//  instructions, search for:  *** DISCONNECT INSTRUCTIONS ***
//
//  Generated On: Wed, 19 Aug 2026 23:52:52 -0400
//  For Domain: www.zen-cart.com
//
//

//  *** DISCONNECT INSTRUCTIONS ***
//
//  You are free to modify or redistribute this software. However, if
//  you do so you must disconnect it from the Project Honey Pot Service.
//  To do this, you must delete the lines of code below located between the
//  *** START CUT HERE *** and *** FINISH CUT HERE *** comments. Under the
//  Terms of Service Use that you agreed to before downloading this software,
//  you may not recreate the deleted lines or modify this software to access
//  or otherwise connect to any Project Honey Pot server.
//
//  *** START CUT HERE ***

define('__REQUEST_HOST', 'hpr9.projecthoneypot.org');
define('__REQUEST_PORT', 80);
define('__REQUEST_SCRIPT', '/cgi/serve.php');

//  *** FINISH CUT HERE ***

interface Response
{
    public function getBody(): ?string;

    public function getLines(): array;
}

class TextResponse implements Response
{
    private $content;

    public function __construct(string $content)
    {
        $this->content = $content;
    }

    public function getBody(): ?string
    {
        return $this->content;
    }

    public function getLines(): array
    {
        return explode("\n", $this->content);
    }
}

interface HttpClient
{
    public function request(string $method, string $url, array $headers = [], array $data = []): Response;
}

class ScriptClient implements HttpClient
{
    private $proxy;

    private $credentials;

    public function __construct(string $settings)
    {
        $this->readSettings($settings);
    }

    private function getAuthorityComponent(?string $authority = null, ?string $tag = null): ?string
    {
        if (is_null($authority)) {
            return null;
        }
        if (! is_null($tag)) {
            $authority .= ":$tag";
        }

        return $authority;
    }

    private function readSettings(string $file): void
    {
        if (! is_file($file) || ! is_readable($file)) {
            return;
        }

        $stmts = file($file);

        $settings = array_reduce($stmts, function ($c, $stmt) {
            [$key, $val] = \array_pad(array_map('trim', explode(':', $stmt)), 2, null);
            $c[$key] = $val;

            return $c;
        }, []);

        $this->proxy = $this->getAuthorityComponent($settings['proxy_host'], $settings['proxy_port']);
        $this->credentials = $this->getAuthorityComponent($settings['proxy_user'], $settings['proxy_pass']);
    }

    public function request(string $method, string $uri, array $headers = [], array $data = []): Response
    {
        $options = [
            'http' => [
                'method' => strtoupper($method),
                'header' => $headers + [$this->credentials ? 'Proxy-Authorization: Basic '.base64_encode($this->credentials) : null],
                'proxy' => $this->proxy,
                'content' => http_build_query($data),
            ],
        ];

        $context = stream_context_create($options);
        $body = file_get_contents($uri, false, $context);

        if ($body === false) {
            exit(
                'Unable to contact the Server. Are outbound connections disabled? '.
                '(If a proxy is required for outbound traffic, you may configure '.
                'the honey pot to use a proxy. For instructions, visit '.
                'http://www.projecthoneypot.org/settings_help.php)'
            );
        }

        return new TextResponse($body);
    }
}

trait AliasingTrait
{
    private $aliases = [];

    public function searchAliases($search, array $aliases, array $collector = [], $parent = null): array
    {
        foreach ($aliases as $alias => $value) {
            if (is_array($value)) {
                return $this->searchAliases($search, $value, $collector, $alias);
            }
            if ($search === $value) {
                $collector[] = $parent ?? $alias;
            }
        }

        return $collector;
    }

    public function getAliases($search): array
    {
        $aliases = $this->searchAliases($search, $this->aliases);

        return ! empty($aliases) ? $aliases : [$search];
    }

    public function aliasMatch($alias, $key)
    {
        return $key === $alias;
    }

    public function setAlias($key, $alias): void
    {
        $this->aliases[$alias] = $key;
    }

    public function setAliases(array $array): void
    {
        array_walk($array, function ($v, $k) {
            $this->aliases[$k] = $v;
        });
    }
}

abstract class Data
{
    protected $key;

    protected $value;

    public function __construct($key, $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    public function key()
    {
        return $this->key;
    }

    public function value()
    {
        return $this->value;
    }
}

class DataCollection
{
    use AliasingTrait;

    private $data;

    public function __construct(Data ...$data)
    {
        $this->data = $data;
    }

    public function set(Data ...$data): void
    {
        array_map(function (Data $data) {
            $index = $this->getIndexByKey($data->key());
            if (is_null($index)) {
                $this->data[] = $data;
            } else {
                $this->data[$index] = $data;
            }
        }, $data);
    }

    public function getByKey($key): ?Data
    {
        $key = $this->getIndexByKey($key);

        return ! is_null($key) ? $this->data[$key] : null;
    }

    public function getValueByKey($key)
    {
        $data = $this->getByKey($key);

        return ! is_null($data) ? $data->value() : null;
    }

    private function getIndexByKey($key)
    {
        $result = [];
        array_walk($this->data, function (Data $data, $index) use ($key, &$result) {
            if ($data->key() == $key) {
                $result[] = $index;
            }
        });

        return ! empty($result) ? reset($result) : null;
    }
}

interface Transcriber
{
    public function transcribe(array $data): DataCollection;

    public function canTranscribe($value): bool;
}

class StringData extends Data
{
    public function __construct($key, string $value)
    {
        parent::__construct($key, $value);
    }
}

class CompressedData extends Data
{
    public function __construct($key, string $value)
    {
        parent::__construct($key, $value);
    }

    public function value()
    {
        $url_decoded = base64_decode(str_replace(['-', '_'], ['+', '/'], $this->value));
        if (substr(bin2hex($url_decoded), 0, 6) === '1f8b08') {
            return gzdecode($url_decoded);
        } else {
            return $this->value;
        }
    }
}

class FlagData extends Data
{
    private $data;

    public function setData($data): void
    {
        $this->data = $data;
    }

    public function value()
    {
        return $this->value ? ($this->data ?? null) : null;
    }
}

class CallbackData extends Data
{
    private $arguments = [];

    public function __construct($key, callable $value)
    {
        parent::__construct($key, $value);
    }

    public function setArgument($pos, $param): void
    {
        $this->arguments[$pos] = $param;
    }

    public function value()
    {
        ksort($this->arguments);

        return \call_user_func_array($this->value, $this->arguments);
    }
}

class DataFactory
{
    private $data;

    private $callbacks;

    private function setData(array $data, string $class, ?DataCollection $dc = null)
    {
        $dc = $dc ?? new DataCollection;
        array_walk($data, function ($value, $key) use ($dc, $class) {
            $dc->set(new $class($key, $value));
        });

        return $dc;
    }

    public function setStaticData(array $data)
    {
        $this->data = $this->setData($data, StringData::class, $this->data);
    }

    public function setCompressedData(array $data)
    {
        $this->data = $this->setData($data, CompressedData::class, $this->data);
    }

    public function setCallbackData(array $data)
    {
        $this->callbacks = $this->setData($data, CallbackData::class, $this->callbacks);
    }

    public function fromSourceKey($sourceKey, $key, $value): ?Data
    {
        $keys = $this->data->getAliases($key);
        $key = reset($keys);
        $data = $this->data->getValueByKey($key);

        switch ($sourceKey) {
            case 'directives':
                $flag = new FlagData($key, $value);
                if (! is_null($data)) {
                    $flag->setData($data);
                }

                return $flag;
            case 'email':
            case 'emailmethod':
                $callback = $this->callbacks->getByKey($key);
                if (! is_null($callback)) {
                    $pos = array_search($sourceKey, ['email', 'emailmethod']);
                    $callback->setArgument($pos, $value);
                    $this->callbacks->set($callback);

                    return $callback;
                }
            default:
                return new StringData($key, $value);
        }
    }

    public static function emailCallback($email, $style = null)
    {
        $value = $email;
        $display = 'style="display:'.['none', ' none'][random_int(0, 1)].'"';
        $style = $style ?? random_int(0, 5);
        $props[] = "href=\"mailto:$email\"";

        $wrap = function ($value, $style) use ($display) {
            switch ($style) {
                case 2: return "<!-- $value -->";
                case 4: return "<span $display>$value</span>";
                case 5:
                    $id = 'chi4a';

                    return "<div id=\"$id\">$value</div>\n<script>document.getElementById('$id').innerHTML = '';</script>";
                default: return $value;
            }
        };

        switch ($style) {
            case 0: $value = '';
                break;
            case 3: $value = $wrap($email, 2);
                break;
            case 1: $props[] = $display;
                break;
        }

        $props = implode(' ', $props);
        $link = "<a $props>$value</a>";

        return $wrap($link, $style);
    }
}

class DataTranscriber implements Transcriber
{
    private $template;

    private $data;

    private $factory;

    private $transcribingMode = false;

    public function __construct(DataCollection $data, DataFactory $factory)
    {
        $this->data = $data;
        $this->factory = $factory;
    }

    public function canTranscribe($value): bool
    {
        if ($value == '<BEGIN>') {
            $this->transcribingMode = true;

            return false;
        }

        if ($value == '<END>') {
            $this->transcribingMode = false;
        }

        return $this->transcribingMode;
    }

    public function transcribe(array $body): DataCollection
    {
        $data = $this->collectData($this->data, $body);

        return $data;
    }

    public function collectData(DataCollection $collector, array $array, $parents = []): DataCollection
    {
        foreach ($array as $key => $value) {
            if ($this->canTranscribe($value)) {
                $value = $this->parse($key, $value, $parents);
                $parents[] = $key;
                if (is_array($value)) {
                    $this->collectData($collector, $value, $parents);
                } else {
                    $data = $this->factory->fromSourceKey($parents[1], $key, $value);
                    if (! is_null($data->value())) {
                        $collector->set($data);
                    }
                }
                array_pop($parents);
            }
        }

        return $collector;
    }

    public function parse($key, $value, $parents = [])
    {
        if (is_string($value)) {
            if (key($parents) !== null) {
                $keys = $this->data->getAliases($key);
                if (count($keys) > 1 || $keys[0] !== $key) {
                    return \array_fill_keys($keys, $value);
                }
            }

            end($parents);
            if (key($parents) === null && strpos($value, '=') !== false) {
                [$key, $value] = explode('=', $value, 2);

                return [$key => urldecode($value)];
            }

            if ($key === 'directives') {
                return explode(',', $value);
            }

        }

        return $value;
    }
}

interface Template
{
    public function render(DataCollection $data): string;
}

class ArrayTemplate implements Template
{
    public $template;

    public function __construct(array $template = [])
    {
        $this->template = $template;
    }

    public function render(DataCollection $data): string
    {
        $output = array_reduce($this->template, function ($output, $key) use ($data) {
            $output[] = $data->getValueByKey($key) ?? null;

            return $output;
        }, []);
        ksort($output);

        return implode("\n", array_filter($output));
    }
}

class Script
{
    private $client;

    private $transcriber;

    private $template;

    private $templateData;

    private $factory;

    public function __construct(HttpClient $client, Transcriber $transcriber, Template $template, DataCollection $templateData, DataFactory $factory)
    {
        $this->client = $client;
        $this->transcriber = $transcriber;
        $this->template = $template;
        $this->templateData = $templateData;
        $this->factory = $factory;
    }

    public static function run(string $host, int $port, string $script, string $settings = ''): void
    {
        $client = new ScriptClient($settings);

        $templateData = new DataCollection;
        $templateData->setAliases([
            'doctype' => 0,
            'head1' => 1,
            'robots' => 8,
            'nocollect' => 9,
            'head2' => 1,
            'top' => 2,
            'legal' => 3,
            'style' => 5,
            'vanity' => 6,
            'bottom' => 7,
            'emailCallback' => ['email', 'emailmethod'],
        ]);

        $factory = new DataFactory;
        $factory->setStaticData([
            'doctype' => '<!DOCTYPE html>',
            'head1' => '<html><head>',
            'head2' => '<title>Www.zen-cart.com Expected</title></head>',
            'top' => '<body><div align="center">',
            'bottom' => '</div></body></html>',
        ]);
        $factory->setCompressedData([
            'robots' => 'H4sIAAAAAAAAA7PJTS1JVMhLzE21VSrKT8ovKVZSSM7PK0nNK7FVSsvPyckv18nLTyxKzsgsSwWyMvNSUiuU7ABZHai-NwAAAA',
            'nocollect' => 'H4sIAAAAAAAAA7PJTS1JVMhLzE21VcrL103NTczM0U3Oz8lJTS7JzM9TUkjOzytJzSuxVdJXsgMAKsBXli0AAAA',
            'legal' => 'H4sIAAAAAAAAA6VabXPUOBL-fr9CF65YqAohAUJImaVqSAaYrSXhZgYoPsq2ZqzFtnySnOzsr79-kWzPxBmWO6oSHFuWWv3y9NMtv_YyLZXIVFk2Ms91vf714PhApMbmytIlPnKNzMKjN_947S3-yt-8Xpnai5XM1K8HmWmtVvbgzcM6dU3Cv8XrNAzKTGnsrw_e0b83q9dP8e6b10_TMF50L9hwB6-WhRK3KnV47Y3YmFY8fHByckoPDV6-TAplhVd446tK-Sn8Pj5NPP53wlNa9fDB-Rk9OE6EL2i8XKvaO2Fodnz0IlHVkVgWeKM29RNv6a2aJufZ0tf_fPJEqD8bWTttatG0VaNy0VjTGCdLYdXKyswbuxFPnrxxOghxhFP0exOj_23vfdGoTNP6JT25AoE-tBXdwb8dSeI3MEw6L9LW1mAhoWpVbURpZI0CNBqs6A5xPIi4trIiXcrWm0p61lMUbVpJXQqZ4_XMC-2EZeHpjdLU6kh8w-tCOiHFTS8JbJf-WCta6UaWrRJmRc_YSPj7JLEoIGveKlUpvHuc_LxqpjIrRCMtTyV5G3VYisY-OvitzVF750m_yegvYKR1f9e1MFmJk5ych2Foc7CpsrQF2MgeF14p0DAJwrvZCDC_JgPS_l7Q3l8FMTIymqw3TQlRgxZS5BrgkUKm5ubndfHwwavzBOIiM3UfGpUEMZqGvTxtHVhuiddfNI8ht0SDuLjgl9litryeL_D602S-_AaBdnaezGkjn98upv_-PKWxMXAh_H-EAXfkHsWC5g4WRJk0G0yJlTUV3mgxvo_hTuaFp7CunFibG9ongoPMMrquRAbeQfGhqgbgDX6k4zhdaUa8xiqICOn1jRIus9JnBcUs-_yqxXlWCBVCEwCoMeFdEB4W5vccadStFEcECfwqUXFL99m2V9GCxLYgLgRcLjKrvc4AWLzV2XdRpigiqqqbjXxMOXxOOqnFF-2C6ky_Xmq8OxS6v5GrIVji7-dJrUIAnCeyZNE3HXTlVjnHu-Q3yRMzs671X4pQgyzwneOAHSxICPMAItRmEKEshKwZkp8aO7gLSmd0u4F9NSb7rryQzplMg7kAdEsFyJ2LwpQbkavMqoi3EEQcTdqNWcsHa33FbPCKhIOJYSb1545x7vHV7yO-OjpQ3xk4CF5aFwIUQTAGv4k5qRQT0j9sdBs_Qe0WHGJCdwV5lx31ybhL2ZBNLOEs6HhsrAxjVR0Wj2kSLJgL16VZSJqpNe26V9E3xBwyUqbXEkzgCc5MHU0OmZdhftzv71Gc-5HiVB2cKHr4HmymRA5AMDLEhCHsyaZSt5TZlJjlQAoGDrrmPdqN86CetJS5EhU4I24Wdifq7e1N3s-n032aXvZjFw8fvDxPri5nV-8pwS_F2_l0cvF_wuyOvsZkWYfpbwsNuW9DVgakC1ZbkeMxm5AZUQeEW1TDupU2F15XijFUWmtuHcxuIFlybLq2qiRHI0Qx-XSHJGJS02K5ptlK7TwuAkNvdN7KsleN1euCDewGuHCrfWFaPwRTcKNP04sZxMzZy2QrLLs7W-nkBHiCCC49phoVNY8qcEcCWRehaq7-jEwq3xd1QFNlRhE8FBMUg6C0hXEStFpECD9PSpWvA36xvkJCCVgL41l1iPQpZaecEFlxxtFe_eKEYqYHtwEatz3znoDL44ZHnhX3B-PoeHlvKnciEGzYNO3DQlYVGVukp4H1wWPhCia9tMNK17hh2lXJmQpSDTpPbspSNKbxpqGszUplLq-tCi4nKKy9BHAKi5HmCr8PtJnLdgXDKRcAIfXBW2BiRiK_Q-v3a2efpu8mlp0wljcQKfuE3hgGMFIOAhTyGl0DLXSgQZCVAUuAJ1IwrABBgOOTc9KW2o4LfpgsxYfJ_MsA1a6-iY9TIocfxPW7fb6y_ECo9tMg9gN2CDQXmTTMPP-4GEY1lYRgKWB-bgA25lbXzGmYpagujkYzhh6EfdA_c09kmPI-HI0WDaUZqR6CL9OmdUhY9LquguZxikfusbgt2HmgFKs9pPMt0oN15Q64zSIdJLDMRNZai1MCdFeOiKED_msVLAWUwgEGO7BsjkteTK8W0y1InG8TRyzf9iDZTVcskGvBBiVyy20VvUhMyoHlGRwBeph4m8YRNQMKbgGxIFukCupOvWpL8MuV9K1EBThdgvwo7yFuc7h9F-p4zDPs17VqrUE1pLosnYDCkzgyPI46hpQjFBSyjNn00kqvW2ROjarbEnKXZKYS83uYGjBTY-qHBNY0pVYO6KorRC7td2KWTK3BDU-ThfjXwwenz5PjCN4vE8h8-7wXE87xM3bFIzEDy9OaDmK6VIBxOVuMiUcow3OGcsO4qdNA6e-A-uQTCHWc_D67mAzNW4M3tNqTTWqGriwkcWPXCj00KyXsMG2ButkVbdllWtVcl-ohNpPgRA6hloEJuKQlXKL66WJQaIBGeTko8AlpIPHVga0VJIBZrTRQ-XLIFSgneCcwxZPtvSjY_BUXLaqEqs-aeic-5tOL6_nl4m7GwQWBIYtZX45gOIfyIIBBI9kO2GpRROkGDLet9X9aRXlolGc3AwoJW9MRKOBnulgCqTsktS0m76ePFo_5eiqW0_lHBgolv7PZFz8Llmj1q8ut0L64vhpaH2tdqoLSUM6ZUGiFkOLiAiGna5pxxGAfISeFSy4kySXhpu-bGSdJGTty8OezkyS05bgbZENdCbbkV_SOyUA589nFcna3iv1ltGqLKFtqLu5BJmPdsJTN-HV5W7KBeauHME_VbK0-GI5EUPNAJqkYcgELAJ6sBNrRZh6QQ4AjAEQ5Cgu3w-BUIGwvqCLhVgz3DiKfhve4pxb5J1dZ51ycM4MLLp4fDdT09vfROmLVed0ZloUvT5OvPyIU27uXme9KfhSHHBPDToQaHFsXIWRA_Jyjng1NPuI534Yy7BE3HSB0U-WJbSlBYiF3E92-uevK1WuNTlarLJrqNMmEY81nhdEWnF5bb7HSKgEe6jVkP6AqUKRhk4N4zM2uUwEpGYYDtbO24FAFeT9Z0wPpaFzH0vBR1y88wLEbwb1Xz1VvrlWBxdAtpBOAMKyJStmwk3Apz_SctwWJxepGecrXsoG3DVASiZq47ZPRKkr7frL8MAV3egZ6P3ueXOG95bWAm2LWW3KciGU9Y3qeTJnF_T1wAb1d0nrL2fXVGKR2GVNVfWe4dtgyVz2IQCVyJN5y-dF3L06T2KAMLoMxDAhC14cCmEFhLGMQNvQL1b8O7BXQkmN81-zbcr55dz2_K3cNVsGyLKRCCL2S4SpXDNHuUHDRYTmxMSAoDIh11-WOPza0-xlmEfSJmbRASZQowFV9sRGAH5DEmXETgBbESiiZ2OhXVkhKN3no4QXcVFwmlpjwNCc8J-gAAzBjvZsAdxPBb0RRyHe2EBb2gvM_S6zJYpcPNqwDyQqN-SH3LUajI7pXKWPH5GACoU7RBHaa4GaOh133BVZf4eSAMAFu3HUNTPykKDo42FNbNuN-QBveUsTnHfK7xY4MTM51Qx6aY3BzFtJB6DBBiUQyCz55CkqUQKxsn7bOTiDPLKg6uZ5TbF5yV2c5e0exNA0zvzxLppQmfioed2BtZ0_Bvb14dBACRiHbrtiBbiB7k2-mpcyIxPpo62i5ZYiqKkIxeCEyAqfZ68IpgYhHbb2LLGkZSmGVdq4z445drq6vnoAaniWfP1JSGNoAKzYXWiBAQpHkczR6bLSIyFBsrNy3PB805CULz0gDIXnDUuTcjA5oFVC44xiBeWeyzmVJLLjQ2XdVM8nfk0kx3ZN4beq8rANuQQ4ImEFrYCccKr99ZdWOhhaXyIW22ZvCY1iRkpvh2UdsnqjA38t8BfUPrFTodcE1kPPtarUBqoLnHWYzLDtZEYbPMshhflBoP08wZA84VVP40mt8yMQJDYpbZanfB7m6dHKlgEtDbNemNukfCFw3qiubKPwhgPZ1jNAhmWlAEZh1bTEoWtBJoul2nOtyp-EHDvaWK7TpUJ3oAACgGZObg8f7rKNXfF5MRZrlgx_pQsOwb_c8OHuVAL9fzieUm-figo7LroD3dwL-zRi_v01b9CY5SyJN13zWjcGi729jRs4sewd4pEft3o3k1izE9ArxjI4b2Hj7OJILXb2SyyR7qyHXEprALFvnn5rUHwrhfQ5IpyG7lr5L7q63AkYEHYV6pTZelFxlSeAVpVlTqwTLWk5VgOMPw3GtoTfCOT3f9HjiOBRBgzNwX6kMtJX5mhk9W4h2K5hlVP0LXHB1h0_9TU7uNBIK43IjOBWRrNgjPxRrMqUUN9qUcqsFvp0NTLtmm3iRbu7zrO4wIuL_KGLFVM8HUYQDHJPEUx8L2H6A79g84XS0z2GQ8dErCCXdWU5MP1AtCQIqvLEGeh0_GLhnr0yuoTDItUQoyiCLaNsY6_EouLUy2wgJ6e8vFU52Ya-QHo64DrlVwrWOWRFnz75G8Fv2X7xjNj4PRzXLz8vpQnDs_3ysjyNQtIl2QC-JePDu2iY1WGjABvJYvQ9LN1ZJJfs-Ryzd7OMIo93nB-rg8dHgrVT1kJ7qbJPhl0CylqhOJ3LVGAgoSCoU1iGd0OcWEGtOtIxIgXiEA_e0LcvcrIXVKVQ54KrxaJY0XkM6N6tdljAf79_QEWLTlMNSAvsasTP14gV7ThG8OEYRUfNKcFwhLAyX8y0AQcPhHXQmAkIF8Og-2kHdELVgGnWrSoKQUkZaTqcTw9In5mkbiRa13zBNoldv73ro0K5lsALYakwJc-I69I1LhFIbyE34gIS4k-lbZ-HUjMOQ7fG1Q0Mnwpc2Xg0SqRJ_tEFOR4QJQzNyQPoQRYUjD4Fkj_RFBckoVrhRdhMIquB5qAD6ATPis-99K4CSeItI4ZHe3VUnlMoTEU4MgHv2DyYXF1Pqli4n_1vohs6njhyTz6AFfcYQ-iMdqOMfnzhrKDkIFfwkKx7Jd-NjYoxeWqgxTTIq3aoU_2s9d7vQzFujG6Bmm67WMPyRjwd6RheH20LScZEx9bYnATt-yuV4aCLu_sAK5CKdzeIHX0rIPh5yAZmTZNboOg-7g77gkG1wMP7CZtSx4ln1QEFtHT652v0Bb-goR_ctjtd8sBxPgI6Gb1CJNr_Ey6uLqcBietQvntInj0_p1Bsu4Pl_Ac3dJow4KQAA',
            'style' => 'H4sIAAAAAAAAAyXMQQqEMAwF0KsIbtXRbSsue49MTbVQ8qUJjCJzdxe-A7xZ7Sq80PDLcccGvSMKqmtDCD5BzH1R1mYaj7Ohmql0SqK9cs3JG5_WrxxRyTLECYT9f_685wNpYPatWwAAAA',
            'vanity' => 'H4sIAAAAAAAAA22S3U7DMAyFX8XKbtk6Bkxa1laIaQghwSZ-LrhMm6wNhDhyzMrenrSMG0CRJVuJv3OcJGdVOQO1cS4GVVvfFGIq-jIorY9lhaQN9VnkgzOFqFT91hB-eC1Hi8Vi2VnNrZydTcPnUpQ5UwoNe-Vs4wvBGH4aj1AJp-ETZikuUpynrm-JMdmmZRnRWT0cGa1Wq56YvHk4MnboWVboNPR6oMgqdxKVj-NoyO6WNTokOZrP58ukLHtPAaNli16ScYrt3iTmZZ711DLPWP-xC8fcmR0L-GX-LKlO0zr_nlZBS2ZXiJY5yCzrum4SCF9NzS16cwjIE6QmE1A7FWMhOlu32GAU5d367mr9AJtr2D5sbterJ7jZ3K9fYLt5yjNV5hX9S__wyfj7pMZ38Qf5mHbgRtHeRDYEW0JORtLocG-4Q3rrocne3mqjoTrA8wAb5IaLyPrHy4ZfUX4BBT7--R0CAAA',
        ]);
        $factory->setCallbackData([
            'emailCallback' => [DataFactory::class, 'emailCallback'],
        ]);

        $transcriber = new DataTranscriber($templateData, $factory);

        $template = new ArrayTemplate([
            'doctype',
            'injDocType',
            'head1',
            'injHead1HTMLMsg',
            'robots',
            'injRobotHTMLMsg',
            'nocollect',
            'injNoCollectHTMLMsg',
            'head2',
            'injHead2HTMLMsg',
            'top',
            'injTopHTMLMsg',
            'actMsg',
            'errMsg',
            'customMsg',
            'legal',
            'injLegalHTMLMsg',
            'altLegalMsg',
            'emailCallback',
            'injEmailHTMLMsg',
            'style',
            'injStyleHTMLMsg',
            'vanity',
            'injVanityHTMLMsg',
            'altVanityMsg',
            'bottom',
            'injBottomHTMLMsg',
        ]);

        $hp = new Script($client, $transcriber, $template, $templateData, $factory);
        $hp->handle($host, $port, $script);
    }

    public function appendVisitPayload(array &$data, $type, array $values)
    {
        $count = count($values);
        if ($count === 0) {
            return;
        }

        $data["has_$type"] = $count;
        foreach ($values as $key => $value) {
            $data["$type|$key"] = $value;
        }
    }

    public function handle($host, $port, $script): void
    {
        $data = [
            'tag1' => 'dccab8d9d8f54d565b94cbd1dd669c86',
            'tag2' => 'dc36c3ce92c200a6858b7a22f046aa54',
            'tag3' => '2777ab7079ed6c18fc916bf33e1ff18c',
            'tag4' => md5_file(__FILE__),
            'version' => 'php-'.phpversion(),
            'ip' => $_SERVER['REMOTE_ADDR'],
            'svrn' => $_SERVER['SERVER_NAME'],
            'svp' => $_SERVER['SERVER_PORT'],
            'sn' => $_SERVER['SCRIPT_NAME'] ?? '',
            'svip' => $_SERVER['SERVER_ADDR'] ?? '',
            'rquri' => $_SERVER['REQUEST_URI'] ?? '',
            'phpself' => $_SERVER['PHP_SELF'] ?? '',
            'ref' => $_SERVER['HTTP_REFERER'] ?? '',
            'uagnt' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];

        if (isset($_POST)) {
            $this->appendVisitPayload($data, 'post', $_POST);
        }

        if (isset($_GET)) {
            $this->appendVisitPayload($data, 'get', $_GET);
        }

        if (isset($_SERVER)) {
            $headers = [];
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $header = strtolower(str_replace('_', '-', substr($key, 5)));
                    $headers[$header] = $value;
                }
            }

            $this->appendVisitPayload($data, 'header', $headers);
        }

        $headers = [
            "User-Agent: PHPot {$data['tag2']}",
            'Content-Type: application/x-www-form-urlencoded',
            'Cache-Control: no-store, no-cache',
            'Accept: */*',
            'Pragma: no-cache',
        ];

        $subResponse = $this->client->request('POST', "http://$host:$port/$script", $headers, $data);
        $data = $this->transcriber->transcribe($subResponse->getLines());
        $response = new TextResponse($this->template->render($data));

        $this->serve($response);
    }

    public function serve(Response $response): void
    {
        header('Cache-Control: no-store, no-cache');
        header('Pragma: no-cache');

        echo $response->getBody();
    }
}

Script::run(__REQUEST_HOST, __REQUEST_PORT, __REQUEST_SCRIPT, __DIR__.'/phpot_settings.php');
