<?php
/*
 Copyright (C) AC SOFTWARE SP. Z O.O.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace SuplaBundle\Supla;

use SuplaApiBundle\Model\CurrentUserAware;
use SuplaBundle\Entity\ClientApp;
use SuplaBundle\Entity\IODeviceChannel;

abstract class SuplaServer {
    use CurrentUserAware;

    /** @var string */
    protected $socketPath;

    public function __construct(string $socketPath) {
        $this->socketPath = $socketPath;
    }

    public function __destruct() {
        $this->disconnect();
    }

    abstract protected function connect();

    abstract protected function disconnect();

    abstract protected function command($command);

    public function oauthAuthorize($userId, $accessToken) {
        $result = false;
        if ($this->connect() !== false) {
            $result = $this->command("OAUTH:" . $accessToken);
        }
        return $result !== false && preg_match("/^AUTH_OK:" . $userId . "\n/", $result) === 1 ? true : false;
    }

    private function isConnected(int $userId, int $id, $what = 'iodev'): bool {
        $userId = intval($userId);
        $id = intval($id);

        if ($userId == 0 || $id == 0) {
            return false;
        }

        switch ($what) {
            case 'client':
                $what = 'CLIENT';
                break;
            default:
                $what = 'IODEV';
                break;
        }

        $result = $this->command("IS-" . $what . "-CONNECTED:" . $userId . "," . $id);
        return $result !== false && preg_match("/^CONNECTED:" . $id . "\n/", $result) === 1 ? true : false;
    }

    public function checkDevicesConnection(int $userId, array $ids) {
        $result = [];
        if ($userId != 0 && $this->connect() !== false) {
            foreach ($ids as $id) {
                if ($this->isConnected($userId, $id) === true) {
                    $result[] = $id;
                }
            }
            $this->disconnect();
        }
        return $result;
    }

    public function isClientAppConnected(ClientApp $clientApp): bool {
        if ($this->connect() !== false) {
            return $this->isConnected($clientApp->getUser()->getId(), $clientApp->getId(), 'client');
        }
        return false;
    }

    public function reconnect($userId = null) {
        if (!$userId) {
            $user = $this->getCurrentUserOrThrow();
            $userId = $user->getId();
        }
        $userId = intval($userId);
        if ($userId != 0 && $this->connect() !== false) {
            $result = $this->command("USER-RECONNECT:" . $userId);
            return $result !== false && preg_match("/^OK:" . $userId . "\n/", $result) === 1 ? true : false;
        }
        return false;
    }

    public function clientReconnect(ClientApp $clientApp) {
        if ($this->connect() !== false) {
            $result = $this->command("CLIENT-RECONNECT:" . $clientApp->getUser()->getId() . "," . $clientApp->getId());
            return $result !== false && preg_match("/^OK:" . $clientApp->getId() . "\n/", $result) === 1 ? true : false;
        }
        return false;
    }

    private function getRawValue($type, IODeviceChannel $channel) {
        if ($this->connect() !== false) {
            $args = [$channel->getUser()->getId(), $channel->getIoDevice()->getId(), $channel->getId()];
            $result = $this->command("GET-" . $type . "-VALUE:" . implode(',', $args));

            if ($result !== false && preg_match("/^VALUE:/", $result) === 1) {
                return $result;
            }
        }
        return false;
    }

    private function setValue(string $type, int $userId, int $deviceId, int $channelId, $value) {
        if ($userId && $deviceId && $channelId && $this->connect()) {
            $result = $this->command("SET-$type-VALUE:$userId,$deviceId,$channelId,$value");
            if ($result && preg_match("/^OK:/", $result) === 1) {
                return true;
            }
        }
        return false;
    }
    
    private function setChannelGroupValue(string $type, int $userId, int $groupId, $value) {
        if ($userId && $deviceId && $channelId && $this->connect()) {
            $result = $this->command("SET-$type-VALUE:$userId,$groupId,$value");
            if ($result && preg_match("/^OK:/", $result) === 1) {
                return true;
            }
        }
        return false;
    }

    private function getValue($type, IODeviceChannel $channel) {
        $result = $this->getRawValue($type, $channel);
        if ($result !== false) {
            list($val) = sscanf($result, "VALUE:%f\n");

            if (is_numeric($val)) {
                return $val;
            };
        }
        return false;
    }

    /** @return int|bool */
    public function getIntValue(IODeviceChannel $channel) {
        $value = $this->getValue('CHAR', $channel);
        return is_numeric($value) ? intval($value) : $value;
    }

    public function getCharValue(IODeviceChannel $channel) {
        return $this->getValue('CHAR', $channel);
    }

    public function getTemperatureValue(IODeviceChannel $channel) {
        return $this->getValue('TEMPERATURE', $channel);
    }

    public function getHumidityValue(IODeviceChannel $channel) {
        return $this->getValue('HUMIDITY', $channel);
    }

    public function getDistanceValue(IODeviceChannel $channel) {
        return $this->getValue('DOUBLE', $channel);
    }

    public function getRgbwValue(IODeviceChannel $channel) {
        $value = $this->getRawValue('RGBW', $channel);
        if ($value !== false) {
            list($color, $color_brightness, $brightness) = sscanf($value, "VALUE:%i,%i,%i\n");
            if (is_numeric($color) && is_numeric($color_brightness) && is_numeric($brightness)) {
                return ['color' => sprintf('0x%06X', $color), 'color_brightness' => $color_brightness, 'brightness' => $brightness];
            }
        }
        return false;
    }

    private function setCharVal(int $userId, int $deviceId, int $cgId, bool $group, $char) {
        $char = intval($char, 0);
        if ($char < 0 || $char > 255) {
            $char = 0;
        }
        if ($group) {
            return $this->setChannelGroupValue('CHAR', $userId, $cgId, $char);
        }
        return $this->setValue('CHAR', $userId, $deviceId, $cgId, $char);
    }
    
    public function setCharValue(int $userId, int $deviceId, int $channelId, $char) {
        return $this->setCharVal($userId, $deviceId, $channelId, false, $char);
    }
    
    public function setChannelGroupCharValue(int $userId, int $groupId, $char) {
        return $this->setCharVal($userId, 0, $channelId, true, $char);
    }

    private function setRgbwVal(int $userId, int $deviceId, int $cgId, bool $group, int $color, int $colorBrightness, int $brightness) {

        $color = intval($color, 0x00FF00);
        $colorBrightness = intval($colorBrightness, 0);
        $brightness = intval($brightness, 0);

        if ($colorBrightness < 0 || $colorBrightness > 255) {
            $colorBrightness = 0;
        }

        if ($brightness < 0 || $brightness > 255) {
            $brightness = 0;
        }

        if ($color < 0 || $color > 0xffffff) {
            $color = 0x00FF00;
        }
        
        if ($group) {
            return $this->setChannelGroupVal('RGBW', $userId, $cgId, $color . ',' . $colorBrightness . ',' . $brightness);
        }

        return $this->setValue('RGBW', $userId, $deviceId, $cgId, $color . ',' . $colorBrightness . ',' . $brightness);
    }
    
    public function setRgbwValue(int $userId, int $deviceId, int $channelId, int $color, int $colorBrightness, int $brightness) {
        return $this->setRgbwVal($userId, $deviceId, $cgId, false, $color, $colorBrightness, $brightness);
    }
    
    public function setChannelGroupRgbwValue(int $userId, int $groupId, int $color, int $colorBrightness, int $brightness) {
        return $this->setRgbwVal($userId, 0, $groupId, true, $color, $colorBrightness, $brightness);
    }

    public function isAlive(): bool {
        // TODO @pzygmunt
        return true;
    }
}
