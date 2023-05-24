<?php

namespace App\Tests\Application;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultTest extends WebTestCase
{
    public function testFunctionality()
    {
        $client = static::createClient();
        self::bootKernel();

        $crawler = $client->request('GET', '_test/api/success');

        $this->assertResponseIsSuccessful();
    }
}
