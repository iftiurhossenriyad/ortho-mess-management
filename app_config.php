<?php
function app_config_value(string $name, string $default = ''): string {
    $environmentValue = getenv($name);
    if ($environmentValue !== false && $environmentValue !== '') {
        return $environmentValue;
    }

    static $deploymentConfig = null;
    if ($deploymentConfig === null) {
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__;
        if ($documentRoot === '') {
            $documentRoot = __DIR__;
        }
        $configPaths = [
            dirname($documentRoot) . DIRECTORY_SEPARATOR . 'ortho-config.php',
            rtrim($documentRoot, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . 'private-config'
                . DIRECTORY_SEPARATOR . 'ortho-config.php',
        ];
        $deploymentConfig = [];
        foreach ($configPaths as $configPath) {
            if (is_file($configPath)) {
                $deploymentConfig = require $configPath;
                if (!is_array($deploymentConfig)) {
                    throw new UnexpectedValueException('Server configuration is invalid.');
                }
                break;
            }
        }
    }

    if (!array_key_exists($name, $deploymentConfig)) {
        return $default;
    }
    if (!is_string($deploymentConfig[$name])) {
        throw new UnexpectedValueException('Server configuration is invalid.');
    }
    return $deploymentConfig[$name];
}
