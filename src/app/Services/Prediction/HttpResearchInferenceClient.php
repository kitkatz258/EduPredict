<?php

declare(strict_types=1);

namespace App\Services\Prediction;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Calls the FastAPI research service with explicit Pianda or UCI payloads.
 * It has no method that accepts a student FeatureSet.
 */
final class HttpResearchInferenceClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly int $timeoutSeconds = 5,
        private readonly int $connectTimeoutSeconds = 2,
    ) {}

    public function scoreEmployability(float $competenceMean, float $psyCapMean): ResearchEmployabilityResult
    {
        $this->assertConfigured();
        if (! is_finite($competenceMean) || ! is_finite($psyCapMean) || $competenceMean < 1 || $competenceMean > 5 || $psyCapMean < 1 || $psyCapMean > 5) {
            throw new ResearchInferenceException('invalid_input', 'Likert means must stay inside [1, 5].');
        }

        $payload = [
            'schema_id' => 'pianda-v1',
            'CompetenceMean' => $competenceMean,
            'PsyCapMean' => $psyCapMean,
        ];
        $this->assertNoBlockedKeys($payload);
        $body = $this->jsonObject($this->post('/v1/research/employability', $payload));
        $this->assertResearchContract($body);
        if (($body['algorithm'] ?? null) !== 'Linear Regression' || ($body['schema_id'] ?? null) !== 'pianda-v1') {
            throw new ResearchInferenceException('contract_mismatch', 'The employability response did not match the Pianda research contract.');
        }
        if (array_key_exists('risk_level', $body) && $body['risk_level'] !== null) {
            throw new ResearchInferenceException('contract_mismatch', 'The employability response included a risk band.');
        }
        if (! isset($body['model_version'], $body['predicted_employability_mean']) || ! is_numeric($body['predicted_employability_mean'])) {
            throw new ResearchInferenceException('contract_mismatch', 'The employability response did not match the Pianda research contract.');
        }
        $readiness = $body['display_readiness_index'] ?? null;
        if ($readiness !== null && ! is_numeric($readiness)) {
            throw new ResearchInferenceException('contract_mismatch', 'The employability response did not match the Pianda research contract.');
        }

        return new ResearchEmployabilityResult(
            modelVersion: (string) $body['model_version'],
            algorithm: 'Linear Regression',
            schemaId: 'pianda-v1',
            sourceDataset: (string) ($body['source_dataset'] ?? ''),
            predictedMean: (float) $body['predicted_employability_mean'],
            displayReadinessIndex: $readiness === null ? null : (float) $readiness,
            researchOnly: true,
            productionValidated: false,
            disclaimer: (string) ($body['disclaimer'] ?? ''),
        );
    }

    /**
     * @param  array<string, int|float>  $features
     */
    public function scoreDropout(string $featureSet, array $features): ResearchDropoutResult
    {
        $this->assertConfigured();
        $spec = $this->schemas()['dropout']['feature_sets'][$featureSet] ?? null;
        if (! is_array($spec)) {
            throw new ResearchInferenceException('schema_mismatch', 'Unknown UCI research feature set.');
        }
        $this->assertNoBlockedKeys($features);
        $expected = $spec['columns'];
        $missing = array_values(array_diff($expected, array_keys($features)));
        $extra = array_values(array_diff(array_keys($features), $expected));
        if ($missing !== [] || $extra !== []) {
            throw new ResearchInferenceException('schema_mismatch', 'Predictor schema mismatch for the requested UCI feature set.');
        }
        foreach ($features as $value) {
            if (is_bool($value) || (! is_int($value) && ! is_float($value)) || (is_float($value) && ! is_finite($value))) {
                throw new ResearchInferenceException('invalid_input', 'Research inputs must be JSON numbers.');
            }
        }

        $payload = [
            'schema_id' => 'uci-697',
            'feature_set' => $featureSet,
            'features' => $features,
        ];
        $body = $this->jsonObject($this->post('/v1/research/dropout', $payload));
        $this->assertResearchContract($body);
        $className = $body['predicted_class_name'] ?? null;
        $predictedClass = $body['predicted_class'] ?? null;
        $names = [0 => 'Not Dropout', 1 => 'Dropout'];
        if (($body['algorithm'] ?? null) !== 'Logistic Regression'
            || ($body['schema_id'] ?? null) !== 'uci-697'
            || ($body['feature_set'] ?? null) !== $featureSet
            || ! is_numeric($body['predicted_dropout_probability'] ?? null)
            || ! is_int($predictedClass)
            || ! isset($names[$predictedClass])
            || $className !== $names[$predictedClass]
        ) {
            throw new ResearchInferenceException('contract_mismatch', 'The dropout response did not match the binary UCI research contract.');
        }
        if (array_key_exists('risk_level', $body) && $body['risk_level'] !== null) {
            throw new ResearchInferenceException('contract_mismatch', 'The dropout response included a risk band.');
        }
        $weak = $featureSet === 'scholarship_only';
        if (($body['weak_compatibility_baseline'] ?? null) !== $weak) {
            throw new ResearchInferenceException('contract_mismatch', 'The dropout response did not identify the scholarship baseline correctly.');
        }
        $probability = (float) $body['predicted_dropout_probability'];
        if ($probability < 0 || $probability > 1) {
            throw new ResearchInferenceException('contract_mismatch', 'The dropout probability was outside 0 to 1.');
        }

        return new ResearchDropoutResult(
            modelVersion: (string) $body['model_version'],
            algorithm: 'Logistic Regression',
            featureSet: $featureSet,
            schemaId: 'uci-697',
            sourceDataset: (string) ($body['source_dataset'] ?? ''),
            probability: $probability,
            predictedClass: $predictedClass,
            predictedClassName: $className,
            researchOnly: true,
            productionValidated: false,
            suitableForStudentAdvice: false,
            weakCompatibilityBaseline: $weak,
            disclaimer: (string) ($body['disclaimer'] ?? ''),
        );
    }

    private function assertConfigured(): void
    {
        $parts = parse_url($this->baseUrl);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? '') : '';
        if ($this->token === '' || ! in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
            throw new ResearchInferenceException('not_configured', 'Research inference is not configured.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload): Response
    {
        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeout())
                ->connectTimeout($this->connectTimeout())
                ->post($this->endpoint($path), $payload);
        } catch (ConnectionException $exception) {
            $timedOut = str_contains(strtolower($exception->getMessage()), 'timed out')
                || str_contains($exception->getMessage(), 'cURL error 28');
            throw new ResearchInferenceException(
                $timedOut ? 'timeout' : 'service_unavailable',
                $timedOut
                    ? 'The research inference service did not respond in time.'
                    : 'The research inference service is unavailable.',
            );
        }

        if ($response->failed()) {
            $this->throwForResponse($response);
        }

        return $response;
    }

    private function throwForResponse(Response $response): never
    {
        $allowed = [
            'invalid_input' => 'The research payload does not match the source schema.',
            'schema_mismatch' => 'The research payload does not match the source schema.',
            'ucc_input_rejected' => 'UCC questionnaire scores and grades are not inputs to the research models.',
            'unsupported_source_code' => 'A UCI source code is outside the audited research file.',
            'out_of_source_range' => 'A research input is outside the audited UCI source range.',
            'unauthorized' => 'Research inference was refused.',
            'inference_timeout' => 'The research inference service did not respond in time.',
            'models_unavailable' => 'The research inference service is not ready.',
            'inference_failed' => 'The research inference service could not complete the request.',
            'payload_too_large' => 'The research payload is too large.',
            'not_found' => 'The research inference endpoint is not available.',
        ];
        $code = 'service_error';
        $message = 'The research inference service could not complete the request.';
        $payload = $response->json();
        $upstream = is_array($payload) ? ($payload['error'] ?? null) : null;
        if (is_string($upstream) && isset($allowed[$upstream])) {
            $code = $upstream;
            $message = $allowed[$upstream];
        } elseif ($response->status() === 401) {
            $code = 'unauthorized';
            $message = $allowed[$code];
        } elseif ($response->status() === 422) {
            $code = 'schema_mismatch';
            $message = $allowed[$code];
        } elseif ($response->status() === 504) {
            $code = 'inference_timeout';
            $message = $allowed[$code];
        } elseif ($response->status() === 503) {
            $code = 'models_unavailable';
            $message = $allowed[$code];
        }

        throw new ResearchInferenceException($code, $message, $response->status());
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonObject(Response $response): array
    {
        $body = $response->json();
        if (! is_array($body) || array_is_list($body)) {
            throw new ResearchInferenceException('service_error', 'The research inference service could not complete the request.', $response->status());
        }

        return $body;
    }

    private function assertResearchContract(array $body): void
    {
        if (($body['research_only'] ?? false) !== true || ($body['production_validated'] ?? true) !== false) {
            throw new ResearchInferenceException('contract_mismatch', 'The research response was not marked as research-only.');
        }
        if (($body['suitable_for_student_advice'] ?? true) !== false || ($body['student_workflow'] ?? true) !== false) {
            throw new ResearchInferenceException('contract_mismatch', 'The research response was marked as a student prediction.');
        }
    }

    private function assertNoBlockedKeys(mixed $payload): void
    {
        if (! is_array($payload)) {
            return;
        }
        foreach ($payload as $key => $value) {
            if (is_string($key) && $this->isBlockedKey($key)) {
                throw new ResearchInferenceException(
                    'ucc_input_rejected',
                    'UCC questionnaire scores, grades, and student fields are not inputs to the research models.',
                );
            }
            if (is_array($value)) {
                $this->assertNoBlockedKeys($value);
            }
        }
    }

    private function isBlockedKey(string $key): bool
    {
        $normalized = strtolower(trim($key));
        if (in_array($normalized, $this->schemas()['blocked_keys'], true)) {
            return true;
        }

        return preg_match('/^(?:a|s|b|c|p|e)\d{1,2}$/', $normalized) === 1
            || preg_match('/^evs\d+$/', $normalized) === 1
            || preg_match('/^psycap\d+$/', $normalized) === 1
            || preg_match('/^sie\d+$/', $normalized) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function schemas(): array
    {
        $decoded = json_decode((string) file_get_contents(__DIR__.'/research-schemas.json'), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new ResearchInferenceException('not_configured', 'Research inference schemas are unavailable.');
        }

        return $decoded;
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }

    private function timeout(): int
    {
        return max(1, $this->timeoutSeconds);
    }

    private function connectTimeout(): int
    {
        return max(1, $this->connectTimeoutSeconds);
    }
}
