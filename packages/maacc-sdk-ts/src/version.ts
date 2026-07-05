/**
 * The semantic version of this SDK client package. Reported to MAACC on every
 * request (`X-Maacc-Sdk-Version`) and in implementation reports so the server can
 * flag clients below its supported minimum. Keep in step with package.json.
 */
export const SDK_VERSION = '0.2.0';

/** The SDK language identifier reported to MAACC. */
export const SDK_LANGUAGE = 'typescript';
