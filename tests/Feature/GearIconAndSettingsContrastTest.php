<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class GearIconAndSettingsContrastTest extends TestCase
{
    public function test_gear_icon_component_renders_svg_correctly(): void
    {
        $rendered = Blade::render('<x-icon.gear class="w-5 h-5 text-indigo-600" />');

        $this->assertStringContainsString('<svg', $rendered);
        $this->assertStringContainsString('w-5 h-5 text-indigo-600', $rendered);
        $this->assertStringContainsString('viewBox="0 0 24 24"', $rendered);
        $this->assertStringContainsString('<circle cx="12" cy="12" r="3"/>', $rendered);
    }

    public function test_gear_icon_component_uses_default_classes(): void
    {
        $rendered = Blade::render('<x-icon.gear />');

        $this->assertStringContainsString('w-4 h-4', $rendered);
    }

    public function test_pomodoro_settings_overlay_uses_solid_opaque_background(): void
    {
        $content = file_get_contents(resource_path('views/components/pomodoro-timer.blade.php'));

        // Verify solid opaque background is used instead of bg-white/98 with backdrop blur
        $this->assertStringContainsString('class="absolute inset-0 bg-white dark:bg-gray-900 z-30 p-4 sm:p-5 flex flex-col justify-between overflow-hidden shadow-2xl rounded-3xl"', $content);
        $this->assertStringNotContainsString('class="absolute inset-0 bg-white/98', $content);

        // Verify custom gear icon is used
        $this->assertStringContainsString('<x-icon.gear', $content);
    }

    public function test_all_settings_components_use_custom_gear_icon(): void
    {
        $viewsToCheck = [
            'resources/views/profile.blade.php',
            'resources/views/livewire/social.blade.php',
            'resources/views/livewire/pages/public-profile.blade.php',
            'resources/views/livewire/layout/navigation.blade.php',
            'resources/views/livewire/layout/top-header.blade.php',
        ];

        foreach ($viewsToCheck as $viewPath) {
            $content = file_get_contents(base_path($viewPath));
            $this->assertStringContainsString('<x-icon.gear', $content, "Failed in {$viewPath}");
            $this->assertStringNotContainsString('⚙️', $content, "Raw gear emoji still found in {$viewPath}");
        }
    }
}
