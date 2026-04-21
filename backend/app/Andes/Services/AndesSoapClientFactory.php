<?php

namespace App\Andes\Services;

use App\Andes\Exceptions\AndesCertificateEmissionException;
use SoapClient;
use SoapHeader;

/**
 * AndesSoapClientFactory
 *
 * Crea instancias de SoapClient configuradas con WS-Security UsernameToken
 * (PasswordDigest) para autenticar con ANDES WS PKI.
 *
 * Spec: OASIS WS-Security UsernameToken Profile 1.0
 * - PasswordDigest = Base64(SHA1(nonce + created + password))
 */
class AndesSoapClientFactory
{
    public function __construct(
        private readonly string $wsdlUrl,
        private readonly string $username,
        private readonly string $password,
    ) {}

    /**
     * Crea un SoapClient con cabecera WS-Security lista.
     *
     * @throws AndesCertificateEmissionException si no se puede crear el cliente
     * @return object SoapClient configurado
     */
    public function create(): object
    {
        try {
            $client = new SoapClient($this->wsdlUrl, [
                'exceptions'         => true,
                'trace'              => config('app.debug', false),
                'connection_timeout' => 30,
                'stream_context'     => stream_context_create([
                    'ssl' => [
                        'verify_peer'       => true,
                        'verify_peer_name'  => true,
                    ],
                ]),
            ]);

            $client->__setSoapHeaders([$this->buildWsSecurityHeader()]);

            return $client;
        } catch (\SoapFault $e) {
            throw new AndesCertificateEmissionException(
                "No se pudo conectar al WS PKI de ANDES: {$e->getMessage()}"
            );
        }
    }

    /**
     * Construye el SoapHeader de WS-Security con PasswordDigest.
     *
     * PasswordDigest = Base64(SHA1(nonce_decoded + created + password))
     */
    private function buildWsSecurityHeader(): SoapHeader
    {
        $nonce   = random_bytes(16);
        $created = gmdate('Y-m-d\TH:i:s\Z');

        $passwordDigest = base64_encode(
            sha1($nonce . $created . $this->password, true)
        );

        $nonceEncoded = base64_encode($nonce);

        $wsseNs   = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
        $wsuNs    = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
        $pwDigest = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest';

        $security = new \SoapVar(
            '<wsse:Security xmlns:wsse="' . $wsseNs . '" xmlns:wsu="' . $wsuNs . '">
                <wsse:UsernameToken>
                    <wsse:Username>' . htmlspecialchars($this->username, ENT_XML1) . '</wsse:Username>
                    <wsse:Password Type="' . $pwDigest . '">' . $passwordDigest . '</wsse:Password>
                    <wsse:Nonce>' . $nonceEncoded . '</wsse:Nonce>
                    <wsu:Created>' . $created . '</wsu:Created>
                </wsse:UsernameToken>
            </wsse:Security>',
            XSD_ANYXML
        );

        return new SoapHeader($wsseNs, 'Security', $security, true);
    }
}


