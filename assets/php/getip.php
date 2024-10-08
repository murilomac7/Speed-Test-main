<?php

// Exibir todos os erros
ini_set('display_errors', '1');
ini_set('error_reporting', E_ALL);

/**
 * Classe para obter o endereço IP remoto.
 */
class RemoteAddress
{
    /**
     * Usar ou não endereços de proxy.
     *
     * @var bool
     */
    protected $useProxy = false;

    /**
     * Lista de IPs de proxies confiáveis.
     *
     * @var array
     */
    protected $trustedProxies = [];

    /**
     * Cabeçalho HTTP para verificar proxies.
     *
     * @var string
     */
    protected $proxyHeader = 'HTTP_X_FORWARDED_FOR';

    /**
     * Obtém o endereço IP do cliente.
     *
     * @return string Endereço IP.
     */
    public function getIpAddress()
    {
        // Verifica se o IP está sendo obtido de um proxy confiável
        $ip = $this->getIpAddressFromProxy();
        if ($ip) {
            return $ip;
        }

        // Caso contrário, pega o IP diretamente
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Tenta obter o endereço IP de um cliente usando proxy.
     *
     * @return false|string
     */
    protected function getIpAddressFromProxy()
    {
        if (!$this->useProxy || (isset($_SERVER['REMOTE_ADDR']) && !in_array($_SERVER['REMOTE_ADDR'], $this->trustedProxies))) {
            return false;
        }

        $header = $this->proxyHeader;
        if (empty($_SERVER[$header])) {
            return false;
        }

        // Extrai IPs do cabeçalho
        $ips = explode(',', $_SERVER[$header]);
        $ips = array_map('trim', $ips); // Remove espaços em branco
        $ips = array_diff($ips, $this->trustedProxies); // Remove IPs de proxies confiáveis

        // Retorna o IP mais à direita (primeiro IP desconhecido)
        return empty($ips) ? false : array_pop($ips);
    }
}

/**
 * Função para fazer a requisição cURL e retornar o ISP baseado no IP.
 *
 * @param string $ip Endereço IP para consulta.
 * @return array Resposta contendo o IP e o ISP.
 */
function getIspFromIp($ip)
{
    // URL para a requisição
    $url = 'https://geoip.maxmind.com/geoip/v2.1/insights/' . $ip;

    // Definir os cabeçalhos
    $headers = [
        'Content-Type: application/json',
        'Authorization: Basic MTA3MTQ0NjpHSWR4UDFfUVE5S2pySVFMeXVCNjFiQmNvUURrZmN2MUJUMVlfbW1r'
    ];

    // Inicializar cURL
    $ch = curl_init();

    // Configurar opções do cURL
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

    // Executar a requisição
    $response = curl_exec($ch);

    // Verificar por erros
    if (curl_errno($ch)) {
        echo 'Erro: ' . curl_error($ch);
        return [];
    }

    // Fechar a conexão cURL
    curl_close($ch);

    // Decodificar a resposta
    $data = json_decode($response);

    // Retorna o IP e o ISP
    return [
        "ip" => $ip,
        "isp" => $data->traits->isp ?? 'Desconhecido'
    ];
}

// Inicializa a classe para obter o IP
$remoteAddress = new RemoteAddress();
$ip = $remoteAddress->getIpAddress();

// Obtém os dados do ISP para o IP fornecido
$response = getIspFromIp($ip);

// Exibir a resposta formatada como JSON
echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
