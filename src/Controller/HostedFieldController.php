<?php
/*
 * This file is part of the HipaySyliusPlugin
 *
 * (c) Smile <dirtech@smile.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Smile\HipaySyliusPlugin\Controller;

use InvalidArgumentException;
use Smile\HipaySyliusPlugin\Registry\ApiCredentialRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

final class HostedFieldController extends AbstractController
{
    private Environment $twig;
    private ApiCredentialRegistry $apiCredentialRegistry;

    public function __construct(
        Environment $twig,
        ApiCredentialRegistry $apiCredentialRegistry
    ) {
        $this->twig = $twig;
        $this->apiCredentialRegistry = $apiCredentialRegistry;
    }

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function renderHostedFieldsAction(Request $request): Response
    {
        $gateway = $request->attributes->get('gateway') ?? null;
        if (null === $gateway) {
            throw new \LogicException('Unable to find gateway in request');
        }

        $config = $this->apiCredentialRegistry->getApiConfig($gateway);
        try {
            return new Response(
                $this->twig->render(
                    '@SmileHipaySyliusPlugin/Checkout/hipay_hosted_fields.html.twig',
                    [
                        'gateway' => $gateway,
                        'apiConfig' => [
                            'username' => $config->getUsername(),
                            'password' => $config->getPassword(),
                            'stage' => $config->getStage(),
                            'locale' => $config->getLocale(),
                        ],
                    ]
                )
            );
        } catch (InvalidArgumentException $exception) {
            return new Response('');
        }
    }
}
