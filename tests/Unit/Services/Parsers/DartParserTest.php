<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Parsers;

use App\Services\Scanner\Parsers\DartParser;
use Tests\TestCase;

class DartParserTest extends TestCase
{
    private DartParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DartParser();
    }

    public function test_it_supports_dart_files_only(): void
    {
        $this->assertTrue($this->parser->supports('/lib/main.dart', 'dart'));
        $this->assertFalse($this->parser->supports('/app/Main.php', 'php'));
    }

    public function test_it_extracts_imports_and_classes_with_inheritance(): void
    {
        $dartCode = <<<'DART'
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/auth_service.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});
}

class _LoginPageState extends State<LoginPage> with SingleTickerProviderStateMixin implements RouteAware {
  // state body
}
DART;

        $result = $this->parser->parse('/lib/login_page.dart', $dartCode);

        $this->assertContains('package:flutter/material.dart', $result['imports']);
        $this->assertContains('package:provider/provider.dart', $result['imports']);
        $this->assertContains('../services/auth_service.dart', $result['imports']);

        $this->assertContains('LoginPage', $result['defines']);
        $this->assertContains('_LoginPageState', $result['defines']);

        $this->assertContains('State<LoginPage>', $result['extends']);
        $this->assertContains('SingleTickerProviderStateMixin', $result['extends']);
        $this->assertContains('RouteAware', $result['extends']);
    }
}
