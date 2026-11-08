<?php

declare(strict_types=1);

namespace App\Templates;

class DrupalTemplate implements TemplateInterface
{
    public function getStack(): array
    {
        return [
            'primary_language' => 'php',
            'frameworks'       => ['drupal' => 'Handles CMS content modeling, entity management, and core routing.'],
            'templating_and_styling' => ['twig' => 'Primary theme layer presentation.'],
        ];
    }

    public function getAgentRules(): array
    {
        return [
            'SYSTEM ROLE: You are an expert PHP and Drupal core developer. Your code must align with modern Drupal architectural patterns.',
            'DEPENDENCY INJECTION: You must prioritize Dependency Injection (DI) via class constructors. Strictly avoid utilizing the static `\Drupal::` service container wrapper in object-oriented code.',
            'CODING STANDARDS: Adhere strictly to official Drupal coding standards. Ensure correct docblock formatting and utilize custom modules following Drupal core conventions.',
            'TEMPLATING: Within Twig files, strictly utilize Twig syntax, filters, and variables. Under no circumstances should you write raw PHP logic inside the presentation layer.',
            'HOOKS & EVENTS: Prefer Symfony Event Subscribers over traditional procedural pre-process hooks when altering core system behaviors.',
        ];
    }

    public function getScripts(): array
    {
        return [
            'test' => './vendor/bin/phpunit',
            'lint' => './vendor/bin/phpcs --standard=Drupal',
        ];
    }

    /**
     * @return array{
     *     include_dirs: array<int, string>,
     *     file_extensions: array<int, string>,
     *     exclude_patterns: array<int, string>,
     *     max_file_size_kb: int,
     *     max_context_tokens: int
     * }
     */
    public function getContextScanning(): array
    {
        return [
            'include_dirs' => ['app', 'src', 'lib', 'resources', 'tests'],
            'file_extensions' => ['php', 'js', 'ts', 'dart', 'blade.php', 'twig', 'scss'],
            'exclude_patterns' => ['node_modules', 'vendor', '.git', 'build', 'dist', '.dart_tool'],
            'max_file_size_kb' => 300,
            'max_context_tokens' => 1200,
        ];
    }

    public function getSchemas(): array
    {
        return [];
    }
}
