<?php

namespace SuplaBundle\Tests\Traits;

use PHPUnit\Framework\MockObject\MockObject;
use SuplaBundle\Entity\Scene;
use SuplaBundle\Enums\ChannelFunction;

/**
 * @method MockObject createMock(string $className)
 */
trait SceneMocks {
    protected function createSceneMock(): Scene {
        $scene = $this->createMock(Scene::class);
        $scene->method('getId')->willReturn(1);
        $scene->method('getFunction')->willReturn(ChannelFunction::SCENE());
        return $scene;
    }
}
