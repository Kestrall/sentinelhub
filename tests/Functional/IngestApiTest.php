<?php

namespace App\Tests\Functional;

use App\Document\TelemetryPoint;
use App\Entity\AlertRule;
use App\Entity\Device;
use App\Entity\Site;
use App\Entity\User;
use App\Enum\Metric;
use App\Enum\Operator;
use App\Repository\AlertRepository;
use App\Security\DeviceTokenManager;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test de bout en bout : HTTP → Symfony → MySQL + MongoDB (bases *_test).
 */
final class IngestApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $token;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $em = $container->get(EntityManagerInterface::class);
        $schema = new SchemaTool($em);
        $schema->dropDatabase();
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());

        $dm = $container->get(DocumentManager::class);
        $dm->getSchemaManager()->dropCollections();

        $user = (new User())->setEmail('test@sentinelhub.dev')->setFullName('Test')->setPassword('x');
        $site = (new Site($user))->setName('Labo');
        $device = (new Device($site))->setName('Capteur test')->setMacAddress('AA:BB:CC:DD:EE:01');
        $device->addRule(new AlertRule($device, Metric::Temperature, Operator::GreaterThan, 30));
        $this->token = (new DeviceTokenManager())->issue($device);

        $em->persist($user);
        $em->persist($site);
        $em->persist($device);
        $em->flush();
    }

    public function testRejectsMissingOrInvalidToken(): void
    {
        $this->postJson('/api/v1/telemetry', ['metrics' => ['temperature' => 20]], token: null);
        self::assertResponseStatusCodeSame(401);

        $this->postJson('/api/v1/telemetry', ['metrics' => ['temperature' => 20]], token: 'shd_'.str_repeat('0', 64));
        self::assertResponseStatusCodeSame(401);
    }

    public function testValidatesPayload(): void
    {
        $this->postJson('/api/v1/telemetry', ['metrics' => []]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testStoresTelemetryInMongoAndRaisesAlertInMysql(): void
    {
        $this->postJson('/api/v1/telemetry', ['metrics' => ['temperature' => 35.2, 'rssi' => -60], 'firmware' => '2.0.0']);

        self::assertResponseStatusCodeSame(202);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(1, $body['alerts']);

        $dm = static::getContainer()->get(DocumentManager::class);
        self::assertSame(1, $dm->getRepository(TelemetryPoint::class)->countSince(new \DateTimeImmutable('-1 hour')));

        $alerts = static::getContainer()->get(AlertRepository::class)->findAll();
        self::assertCount(1, $alerts);
        self::assertSame(35.2, $alerts[0]->getValue());
    }

    public function testArmedDeviceTurnsMotionIntoCriticalAlert(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getRepository(Device::class)->findOneBy([])->setArmed(true);
        $em->flush();

        $this->postJson('/api/v1/events', ['type' => 'motion']);

        self::assertResponseStatusCodeSame(201);
        $event = json_decode($this->client->getResponse()->getContent(), true)['event'];
        self::assertSame('critical', $event['severity']);
    }

    public function testDashboardRequiresLogin(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/login');

        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Connexion');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postJson(string $uri, array $payload, ?string $token = 'default'): void
    {
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if (null !== $token) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.('default' === $token ? $this->token : $token);
        }

        $this->client->request('POST', $uri, server: $headers, content: json_encode($payload));
    }
}
