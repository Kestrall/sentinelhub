<?php

namespace App\ValueResolver;

use App\Entity\Device;
use App\Repository\DeviceRepository;
use App\Security\DeviceTokenManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Injecte dans le contrôleur l'appareil authentifié par son en-tête
 * "Authorization: Bearer shd_xxx". Utilisation :
 *
 *     public function __invoke(#[ValueResolver('device_token')] Device $device)
 */
#[AsTargetedValueResolver('device_token')]
final class DeviceTokenResolver implements ValueResolverInterface
{
    public function __construct(private readonly DeviceRepository $devices)
    {
    }

    /** @return iterable<Device> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (Device::class !== $argument->getType()) {
            return [];
        }

        $header = (string) $request->headers->get('Authorization', '');
        $token = str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';

        $device = DeviceTokenManager::looksValid($token)
            ? $this->devices->findOneByTokenHash(DeviceTokenManager::hash($token))
            : null;

        if (!$device) {
            throw new UnauthorizedHttpException('Bearer realm="sentinelhub"', 'Token d\'appareil invalide ou absent.');
        }

        return [$device];
    }
}
