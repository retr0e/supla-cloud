<?php

namespace SuplaBundle\Model\ChannelParamsUpdater\ChannelParamsConfig;

use SuplaBundle\Entity\IODeviceChannel;
use SuplaBundle\Enums\ChannelFunction;
use SuplaBundle\Utils\NumberUtils;

class TemperatureAdjustmentParamTranslator implements ChannelParamTranslator {
    public function getConfigFromParams(IODeviceChannel $channel): array {
        return [
            'temperatureAdjustment' => NumberUtils::maximumDecimalPrecision($channel->getParam2() / 100, 2),
        ];
    }

    public function setParamsFromConfig(array $config, IODeviceChannel $channel) {
        if (isset($config['temperatureAdjustment'])) {
            $channel->setParam2(intval($config['temperatureAdjustment'] * 100));
        }
    }

    public function supports(IODeviceChannel $channel): bool {
        return in_array($channel->getFunction()->getId(), [
            ChannelFunction::THERMOMETER,
            ChannelFunction::HUMIDITYANDTEMPERATURE,
        ]);
    }
}
