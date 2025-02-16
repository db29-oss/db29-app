<?php

namespace App\Services;

use Exception;
use InvalidArgumentException;

class AWS_SES {
    const SMTP_REGIONS = [
        "us-east-2",      // US East (Ohio)
        "us-east-1",      // US East (N. Virginia)
        "us-west-2",      // US West (Oregon)
        "ap-south-1",     // Asia Pacific (Mumbai)
        "ap-northeast-2", // Asia Pacific (Seoul)
        "ap-southeast-1", // Asia Pacific (Singapore)
        "ap-southeast-2", // Asia Pacific (Sydney)
        "ap-northeast-1", // Asia Pacific (Tokyo)
        "ca-central-1",   // Canada (Central)
        "eu-central-1",   // Europe (Frankfurt)
        "eu-west-1",      // Europe (Ireland)
        "eu-west-2",      // Europe (London)
        "eu-south-1",     // Europe (Milan)
        "eu-north-1",     // Europe (Stockholm)
        "sa-east-1",      // South America (Sao Paulo)
        "us-gov-west-1",  // AWS GovCloud (US)
        "us-gov-east-1",  // AWS GovCloud (US)
    ];

    // Constants required for the signature calculation.
    private const DATE     = "11111111";
    private const SERVICE  = "ses";
    private const MESSAGE  = "SendRawEmail";
    private const TERMINAL = "aws4_request";
    private const VERSION  = 0x04;

    /**
     * Generates an SMTP password from the provided AWS Secret Access Key and region.
     *
     * @param string $secret The AWS Secret Access Key.
     * @param string $region The AWS region where the SMTP endpoint is used.
     * @return string The SMTP password (Base64 encoded).
     * @throws InvalidArgumentException if the region doesn't support an SMTP endpoint.
     */
    public static function generateSmtpPassword($secret, $region) {
        if (!in_array($region, self::SMTP_REGIONS)) {
            throw new InvalidArgumentException("The region \"$region\" doesn't have an SMTP endpoint.");
        }

        // Perform the series of HMAC-SHA256 operations.
        $signature = self::sign("AWS4" . $secret, self::DATE);
        $signature = self::sign($signature, $region);
        $signature = self::sign($signature, self::SERVICE);
        $signature = self::sign($signature, self::TERMINAL);
        $signature = self::sign($signature, self::MESSAGE);

        // Prepend the version byte.
        $version_byte = chr(self::VERSION);
        $signature_and_version = $version_byte . $signature;

        // Return the Base64 encoded SMTP password.
        return base64_encode($signature_and_version);
    }

    /**
     * Computes an HMAC-SHA256 signature.
     *
     * @param string $key The key used for signing.
     * @param string $msg The message to sign.
     * @return string The raw binary HMAC.
     */
    private static function sign($key, $msg) {
        return hash_hmac('sha256', $msg, $key, true);
    }
}
