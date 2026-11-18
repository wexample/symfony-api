<?php

namespace Wexample\SymfonyApi\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Wexample\SymfonyApi\Service\ApiDocumentationService;

/**
 * What the api's page draws, read from the OpenAPI document of its area.
 */
class ApiDocumentationPageTest extends KernelTestCase
{
    public function testTheOperationsAreDrawnFromTheDocument(): void
    {
        $documentation = $this->documentation(['area' => 'default', 'access_role' => null, 'preview_paths' => []]);
        $document = $documentation->getDocument();
        $groups = $documentation->getOperationGroups($document, preview: false);
        $operations = array_column($groups['device'], null, 'id');

        // A list: its query options, its paginated answer as an example.
        $list = $operations['get-api-device-readings'];
        $this->assertSame('GET', $list['method']);
        $this->assertContains('sort', array_column($list['parameters'], 'name'));
        $this->assertNotEmpty($list['security']);
        $this->assertStringContainsString('"pagination"', array_column($list['responses'], 'example', 'code')['200']);

        // A body: its fields by their path, required ones marked, and an
        // example to send.
        $post = $operations['post-api-device-readings'];
        $fields = array_column($post['body']['fields'], null, 'name');
        $this->assertTrue($fields['items[].data.value']['required']);
        $this->assertSame('number (float)', $fields['items[].data.value']['type']);
        $this->assertArrayHasKey('value', json_decode($post['body']['example'], true)['items'][0]['data']);
    }

    public function testAPreviewShowsItsPathsOnly(): void
    {
        $documentation = $this->documentation(['area' => 'default', 'access_role' => 'ROLE_ADMIN', 'preview_paths' => ['^/api/device/whoami$']]);
        $groups = $documentation->getOperationGroups($documentation->getDocument(), preview: true);

        $this->assertSame(['/api/device/whoami'], array_column(array_merge(...array_values($groups)), 'path'));
        $this->assertTrue($documentation->hasPreview());
        $this->assertSame('ROLE_ADMIN', $documentation->getAccessRole());
    }

    private function documentation(array $configuration): ApiDocumentationService
    {
        self::bootKernel();

        return new ApiDocumentationService($configuration, self::getContainer()->get('nelmio_api_doc.generator_locator'));
    }
}
