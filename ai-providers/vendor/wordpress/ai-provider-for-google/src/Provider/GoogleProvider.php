<?php

declare(strict_types=1);

namespace WordPress\GoogleAiProvider\Provider;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\GoogleAiProvider\Metadata\GoogleModelMetadataDirectory;
use WordPress\GoogleAiProvider\Models\GoogleImageGenerationModel;
use WordPress\GoogleAiProvider\Models\GoogleTextGenerationModel;

/**
 * Class for the Google provider.
 *
 * @since 1.0.0
 */
class GoogleProvider extends AbstractApiProvider
{
    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function baseUrl(): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        /*
         * Temporary workaround: We don't have a clean way of returning multimodal output model classes yet,
         * currently the implementations are separated by capability. There are a few Google models that support both
         * text and image generation, so if we detect that the model supports image generation, we'll return the image
         * generation model class in that case, because it's far more likely that users want to use those models for
         * image generation. We will have to revisit that in the near future for a proper solution.
         */
        $capabilitiesStringList = $modelMetadata->toArray()[ModelMetadata::KEY_SUPPORTED_CAPABILITIES];
        if (in_array('image_generation', $capabilitiesStringList, true)) {
            return new GoogleImageGenerationModel($modelMetadata, $providerMetadata);
        }

        $capabilities = $modelMetadata->getSupportedCapabilities();
        foreach ($capabilities as $capability) {
            if ($capability->isTextGeneration()) {
                return new GoogleTextGenerationModel($modelMetadata, $providerMetadata);
            }
            if ($capability->isImageGeneration()) {
                return new GoogleImageGenerationModel($modelMetadata, $providerMetadata);
            }
        }

        throw new RuntimeException(
            'Unsupported model capabilities: ' . implode(', ', $capabilities)
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createProviderMetadata(): ProviderMetadata
    {
        $providerMetadataArgs = [
            'google',
            'Google',
            ProviderTypeEnum::cloud(),
            'https://aistudio.google.com/app/api-keys',
            RequestAuthenticationMethod::apiKey()
        ];
        // Provider description support was added in 1.2.0.
        if (version_compare(AiClient::VERSION, '1.2.0', '>=')) {
            // For WordPress, we should translate the description.
            if (function_exists('__')) {
                // phpcs:ignore Generic.Files.LineLength.TooLong
                $providerMetadataArgs[] = __('Text and image generation with Gemini and Imagen.', 'ai-provider-for-google');
            } else {
                $providerMetadataArgs[] = 'Text and image generation with Gemini and Imagen.';
            }
        }
        return new ProviderMetadata(...$providerMetadataArgs);
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        // Check valid API access by attempting to list models.
        return new ListModelsApiBasedProviderAvailability(
            static::modelMetadataDirectory()
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new GoogleModelMetadataDirectory();
    }
}
