export const browserTestBaseUrl = 'http://127.0.0.1:8010';

const database = process.env.BROWSER_DB_DATABASE || 'uhlms_testing';

if (!database.endsWith('_testing')) {
    throw new Error('BROWSER_DB_DATABASE must end with _testing.');
}

export const browserEnvironment = {
    ...process.env,
    APP_ENV: 'testing',
    APP_DEBUG: 'false',
    APP_URL: browserTestBaseUrl,
    BCRYPT_ROUNDS: '12',
    CACHE_STORE: 'array',
    CONTENT_SECURITY_POLICY_MODE: 'enforce',
    DB_CONNECTION: 'mysql',
    DB_HOST: process.env.BROWSER_DB_HOST || '127.0.0.1',
    DB_PORT: process.env.BROWSER_DB_PORT || '3306',
    DB_DATABASE: database,
    DB_USERNAME: process.env.BROWSER_DB_USERNAME || 'root',
    DB_PASSWORD: process.env.BROWSER_DB_PASSWORD || '',
    HASH_VERIFY: 'false',
    MAIL_MAILER: 'array',
    PUBLIC_HTTPS_ENFORCED: 'false',
    QUEUE_CONNECTION: 'sync',
    SESSION_DRIVER: 'database',
    TRUSTED_HOSTS: 'localhost,127.0.0.1,::1',
};
