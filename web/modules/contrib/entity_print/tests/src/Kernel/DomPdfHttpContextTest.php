<?php

namespace Drupal\Tests\entity_print\Kernel;

use Drupal\entity_print\Plugin\EntityPrint\PrintEngine\DomPdf;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the session cookie and credentials are scoped to the site's host.
 *
 * @coversDefaultClass \Drupal\entity_print\Plugin\EntityPrint\PrintEngine\DomPdf
 * @group entity_print
 */
class DomPdfHttpContextTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'entity_print'];

  /**
   * @covers ::setupHttpContext
   * @covers ::scopeHttpContextHeaders
   */
  public function testHeadersScopedToOwnHost(): void {
    if (!class_exists('Dompdf\Dompdf')) {
      $this->markTestSkipped('Dompdf is not installed.');
    }
    $session = new Session(new MockArraySessionStorage(), NULL, NULL);
    $session->setName('SSESStest');
    $session->setId('victim-session-id');
    $request = Request::create('https://example.com/print/pdf/node/1');
    $request->setSession($session);

    $engine = new DomPdf(
      ['username' => 'user', 'password' => 'pass'],
      'dompdf',
      ['export_type' => 'pdf'],
      $this->container->get('plugin.manager.entity_print.export_type')->createInstance('pdf'),
      $request,
      $this->container->get('file_system'),
      $session,
    );
    $setup = new \ReflectionMethod($engine, 'setupHttpContext');
    $setup->setAccessible(TRUE);
    $setup->invoke($engine);

    /** @var \Dompdf\Dompdf $dompdf */
    $dompdf = $engine->getPrintObject();
    $context = $dompdf->getHttpContext();
    $this->assertFalse(stream_context_get_options($context)['http']['follow_location']);
    $rules = $dompdf->getOptions()->getAllowedProtocols()['https://']['rules'];

    $sensitive = [
      'Authorization: Basic ' . base64_encode('user:pass'),
      'Cookie: SSESStest=victim-session-id',
    ];
    $cases = [
      'https://example.com/system/files/a.png' => $sensitive,
      'https://example.com:443/a.png' => $sensitive,
      'https://EXAMPLE.com/a.png' => $sensitive,
      'https://attacker.test/steal.png' => [],
      'https://example.com.attacker.test/a.png' => [],
      'https://example.com@attacker.test/a.png' => [],
      'https://example.com:8443/a.png' => [],
      'http://example.com/a.png' => [],
      'https://169.254.169.254/latest/meta-data' => [],
    ];
    foreach ($cases as $url => $expected) {
      foreach ($rules as $rule) {
        [$result] = $rule($url);
        $this->assertTrue($result, $url);
      }
      $this->assertSame($expected, stream_context_get_options($context)['http']['header'], $url);
    }
  }

}
