<?php
/**
 * Sparrow SMS API Integration Class
 * API Documentation: https://docs.sparrowsms.com/
 */

class SparrowSMS {
    private $token;
    private $from;
    private $baseUrl = 'https://api.sparrowsms.com/v2/';
    private $logFile;    public function __construct($token = null, $from = null) {
        try {
            $this->token = $token ?: $this->getConfigValue('api_token');
            $this->from = $from ?: $this->getConfigValue('sender_identity');
            
            // Update base URL from config if available
            $apiEndpoint = $this->getConfigValue('api_endpoint');
            if ($apiEndpoint) {
                $this->baseUrl = rtrim($apiEndpoint, '/') . '/';
            }
            
            // Set default values if config values are not available
            if (!$this->token) {
                $this->token = 'default_token'; // Will need to be configured
            }
            if (!$this->from) {
                $this->from = 'HRMS'; // Default sender name
            }
            
            $this->logFile = __DIR__ . '/logs/sms_' . date('Y_m_d') . '.log';
            
            // Create logs directory if not exists
            if (!is_dir(__DIR__ . '/logs')) {
                mkdir(__DIR__ . '/logs', 0755, true);
            }
        } catch (Exception $e) {
            // Fallback initialization if database is not available
            $this->token = $token ?: 'default_token';
            $this->from = $from ?: 'HRMS';
            $this->logFile = __DIR__ . '/logs/sms_' . date('Y_m_d') . '.log';
            
            // Create logs directory if not exists
            if (!is_dir(__DIR__ . '/logs')) {
                mkdir(__DIR__ . '/logs', 0755, true);
            }
            
            $this->log("SMS class initialized with fallback values due to: " . $e->getMessage(), 'WARNING');
        }
    }
      /**
     * Get configuration value from database or config file
     */    private function getConfigValue($key) {
        try {
            require_once __DIR__ . '/../../includes/db_connection.php';
            global $pdo;
            
            // Check if PDO connection is available
            if (!isset($pdo) || $pdo === null) {
                $this->log("Database connection not available for config key: $key", 'WARNING');
                return null;
            }
            
            $stmt = $pdo->prepare("SELECT config_value FROM sms_config WHERE config_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['config_value'] : null;
        } catch (Exception $e) {
            $this->log("Error getting config value for $key: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }
    
    /**
     * Send single SMS
     */
    public function sendSMS($to, $text, $from = null) {
        $from = $from ?: $this->from;
        
        $data = [
            'token' => $this->token,
            'from' => $from,
            'to' => $to,
            'text' => $text
        ];
        
        $response = $this->makeRequest('sms', $data, 'POST');
        
        // Log the SMS
        $this->logSMS($to, $text, $from, $response);
        
        return $response;
    }
    
    /**
     * Send bulk SMS
     */
    public function sendBulkSMS($recipients, $text, $from = null) {
        $from = $from ?: $this->from;
        
        $data = [
            'token' => $this->token,
            'from' => $from,
            'to' => implode(',', $recipients),
            'text' => $text
        ];
        
        $response = $this->makeRequest('sms', $data, 'POST');
        
        // Log bulk SMS
        foreach ($recipients as $recipient) {
            $this->logSMS($recipient, $text, $from, $response);
        }
        
        return $response;
    }    /**
     * Check SMS credit balance
     */
    public function checkCredit() {
        $data = ['token' => $this->token];
        $response = $this->makeRequest('credit', $data, 'GET');
        
        // Handle the specific response format from Sparrow SMS Credits API
        if ($response['success']) {
            // Try different possible field names for credit balance
            $creditBalance = $response['credit_balance'] ?? 
                            $response['credits_available'] ?? 
                            $response['credit'] ?? 
                            $response['balance'] ?? 0;
            
            return [
                'success' => true,
                'credit_balance' => $creditBalance,
                'credits_consumed' => $response['credits_consumed'] ?? 0,
                'response_code' => $response['response_code'] ?? 200,
                'raw_response' => $response
            ];
        } else {
            // Extract specific error message from API response
            $errorMessage = 'Unknown error';
            
            // Check for specific API error response format
            if (isset($response['response']) && is_array($response['response'])) {
                // Handle structured response
                $errorMessage = $response['response']['response'] ?? 
                               $response['response']['message'] ?? 
                               $response['response']['error'] ?? $errorMessage;
            } elseif (isset($response['response']) && is_string($response['response'])) {
                // Handle direct string response
                $errorMessage = $response['response'];
            } elseif (isset($response['error'])) {
                // Handle error field
                $errorMessage = $response['error'];
            }
            
            // Check for specific error codes
            if (isset($response['response_code'])) {
                switch ($response['response_code']) {
                    case 1002:
                        $errorMessage = 'Invalid API Token - Please check your Sparrow SMS API token';
                        break;
                    case 1001:
                        $errorMessage = 'Insufficient SMS credits';
                        break;
                    case 1003:
                        $errorMessage = 'IP not whitelisted';
                        break;
                }
            }
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'http_code' => $response['http_code'] ?? 0,
                'response_code' => $response['response_code'] ?? null,
                'raw_response' => $response
            ];
        }
    }
    
    /**
     * Get SMS status
     */
    public function getSMSStatus($messageId) {
        $data = [
            'token' => $this->token,
            'id' => $messageId
        ];
        
        return $this->makeRequest('status', $data, 'GET');
    }
    
    /**
     * Get SMS logs from API
     */
    public function getSMSLogs($from_date = null, $to_date = null, $page = 1, $limit = 100) {
        $data = [
            'token' => $this->token,
            'page' => $page,
            'limit' => $limit
        ];
        
        if ($from_date) {
            $data['from'] = $from_date;
        }
        
        if ($to_date) {
            $data['to'] = $to_date;
        }
        
        return $this->makeRequest('logs', $data, 'GET');
    }
    
    /**
     * Verify phone number format
     */
    public function verifyPhoneNumber($number) {
        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $number);
        
        // Nepal phone number validation
        if (preg_match('/^977[0-9]{10}$/', $cleaned) || preg_match('/^[0-9]{10}$/', $cleaned)) {
            // Add country code if not present
            if (strlen($cleaned) == 10) {
                $cleaned = '977' . $cleaned;
            }
            return $cleaned;
        }
        
        return false;
    }
      /**
     * Make HTTP request to Sparrow SMS API
     */    private function makeRequest($endpoint, $data, $method = 'POST') {
        // Ensure endpoint has trailing slash to avoid 301 redirects
        $endpoint = rtrim($endpoint, '/') . '/';
        $url = $this->baseUrl . $endpoint;
        
        // Log the request details for debugging
        $this->log("Making $method request to: $url", 'INFO');
        $this->log("Request data: " . json_encode($data), 'DEBUG');
        
        $curl = curl_init();
        
        if ($method === 'GET') {
            $url .= '?' . http_build_query($data);
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'PHP-HRMS-SMS/1.0',
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json'
                ]
            ]);
        } else {
            // Use form data format instead of JSON for POST requests
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($data),
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'PHP-HRMS-SMS/1.0',
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json'
                ]
            ]);
        }
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        $curlInfo = curl_getinfo($curl);
        curl_close($curl);
        
        // Log detailed response information
        $this->log("Response HTTP Code: $httpCode", 'DEBUG');
        $this->log("Response size: " . strlen($response) . " bytes", 'DEBUG');
        
        if ($error) {
            $this->log("CURL Error: $error", 'ERROR');
            return [
                'success' => false,
                'error' => $error,
                'http_code' => $httpCode
            ];
        }
        
        // Log raw response for debugging
        $this->log("Raw API response: " . substr($response, 0, 500) . (strlen($response) > 500 ? '...' : ''), 'DEBUG');
        
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("JSON decode error: " . json_last_error_msg(), 'ERROR');
            $this->log("Raw response: " . $response, 'ERROR');
            return [
                'success' => false,
                'error' => 'Invalid JSON response: ' . json_last_error_msg(),
                'http_code' => $httpCode,
                'raw_response' => $response
            ];
        }
          if ($httpCode >= 200 && $httpCode < 300) {
            $this->log("API Request successful: $endpoint", 'INFO');
            return array_merge($decodedResponse ?: [], ['success' => true, 'http_code' => $httpCode]);
        } else {
            $this->log("API Request failed: $endpoint - HTTP $httpCode - $response", 'ERROR');
            
            // Enhanced error response parsing for Sparrow SMS API
            $errorResponse = [
                'success' => false,
                'http_code' => $httpCode,
                'raw_response' => $response
            ];
            
            if ($decodedResponse) {
                // Merge the API response data
                $errorResponse = array_merge($errorResponse, $decodedResponse);
                
                // Extract error message with priority order
                $errorMessage = $decodedResponse['response'] ?? 
                               $decodedResponse['message'] ?? 
                               $decodedResponse['error'] ?? 
                               'HTTP ' . $httpCode . ' error';
                               
                $errorResponse['error'] = $errorMessage;
            } else {
                $errorResponse['error'] = 'HTTP ' . $httpCode . ' error - Invalid response format';
            }
            
            return $errorResponse;
        }
    }
    
    /**
     * Log SMS to database
     */    private function logSMS($to, $text, $from, $response) {
        try {
            require_once __DIR__ . '/../../includes/db_connection.php';
            global $pdo;
            
            $stmt = $pdo->prepare("
                INSERT INTO sms_logs (
                    phone_number, message, sender, 
                    status, message_id, response_data, 
                    cost, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $status = $response['success'] ? 'sent' : 'failed';
            $messageId = $response['id'] ?? null;
            $cost = $response['cost'] ?? 0;
            
            $stmt->execute([
                $to,
                $text,
                $from,
                $status,
                $messageId,
                json_encode($response),
                $cost
            ]);
            
        } catch (Exception $e) {
            $this->log("Error logging SMS to database: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * Log messages to file
     */    private function log($message, $level = 'INFO') {
        // Ensure logFile is set
        if (!$this->logFile) {
            $this->logFile = __DIR__ . '/logs/sms_' . date('Y_m_d') . '.log';
            
            // Create logs directory if not exists
            if (!is_dir(__DIR__ . '/logs')) {
                mkdir(__DIR__ . '/logs', 0755, true);
            }
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Format phone number for display
     */
    public function formatPhoneNumber($number) {
        $cleaned = preg_replace('/[^0-9]/', '', $number);
        
        if (strlen($cleaned) >= 10) {
            if (substr($cleaned, 0, 3) == '977') {
                $cleaned = substr($cleaned, 3);
            }
            
            return substr($cleaned, 0, 3) . '-' . substr($cleaned, 3, 3) . '-' . substr($cleaned, 6);
        }
        
        return $number;
    }
    
    /**
     * Get SMS statistics
     */
    public function getSMSStatistics($days = 30) {        try {
            require_once __DIR__ . '/../../includes/db_connection.php';
            global $pdo;
            
            // Total SMS sent in last X days
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_sent,
                    COUNT(CASE WHEN status = 'sent' THEN 1 END) as successful,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                    SUM(cost) as total_cost,
                    DATE(created_at) as date
                FROM sms_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");
            
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->log("Error getting SMS statistics: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
      /**
     * Validate a sender identity against Sparrow SMS API
     * This tests if a sender ID is approved by sending a test SMS (without actually sending)
     */
    public function validateSenderIdentity($senderIdentity) {
        try {
            // Make a test call to check if sender identity is valid
            // We'll use an invalid phone number to avoid sending actual SMS
            $data = [
                'token' => $this->token,
                'from' => $senderIdentity,
                'to' => '9999999999', // Invalid number to avoid actual sending
                'text' => 'Test validation'
            ];
            
            $response = $this->makeRequest('sms', $data, 'POST');
            
            // Check response for sender validity
            if (isset($response['response_code'])) {
                // If we get "Invalid Sender" error (1008), the sender ID is not approved
                if ($response['response_code'] == 1008) {
                    return false;
                }
                // If we get "Invalid Receiver" (1007) or other errors except sender error,
                // it means sender ID is valid but phone number is invalid (which is expected)
                if ($response['response_code'] == 1007 || $response['response_code'] == 1011) {
                    return true;
                }
            }
            
            // For any other response, assume valid for safety
            return true;
        } catch (Exception $e) {
            $this->log("Error validating sender identity: " . $e->getMessage(), 'ERROR');
            return true; // Assume valid if we can't check
        }
    }
    
    /**
     * Get all sender identities from sms_config (JSON array)
     */
    public function getSenderIdentities() {
        $identities = [];
        $json = $this->getConfigValue('sender_identities');
        if ($json) {
            $identities = json_decode($json, true) ?: [];
        }
        // Always return at least one default
        if (empty($identities)) {
            $identities = [[
                'identity' => $this->from ?: 'HRMS',
                'description' => 'Default HRMS Identity',
                'is_default' => true
            ]];
        }
        return $identities;
    }

    /**
     * Add a sender identity to sms_config (JSON array)
     */
    public function addSenderIdentity($identity, $description = '', $setDefault = false) {
        $identity = strtoupper(trim($identity));
        if (!preg_match('/^[A-Z0-9]{1,11}$/', $identity)) {
            return ['success' => false, 'message' => 'Sender identity must be alphanumeric and max 11 chars'];
        }
        $identities = $this->getSenderIdentities();
        foreach ($identities as $id) {
            if ($id['identity'] === $identity) {
                return ['success' => false, 'message' => 'Sender identity already exists'];
            }
        }
        if ($setDefault) {
            foreach ($identities as &$id) { $id['is_default'] = false; }
        }
        $identities[] = [
            'identity' => $identity,
            'description' => $description,
            'is_default' => $setDefault
        ];
        $this->saveSenderIdentities($identities);
        return ['success' => true, 'message' => 'Sender identity added'];
    }

    /**
     * Remove a sender identity from sms_config (JSON array)
     */
    public function removeSenderIdentity($identity) {
        $identity = strtoupper(trim($identity));
        $identities = $this->getSenderIdentities();
        $new = [];
        $found = false;
        foreach ($identities as $id) {
            if ($id['identity'] === $identity) {
                if (!empty($id['is_default'])) {
                    return ['success' => false, 'message' => 'Cannot remove default sender identity'];
                }
                $found = true;
                continue;
            }
            $new[] = $id;
        }
        if (!$found) return ['success' => false, 'message' => 'Sender identity not found'];
        $this->saveSenderIdentities($new);
        return ['success' => true, 'message' => 'Sender identity removed'];
    }

    /**
     * Set a sender identity as default
     */
    public function setDefaultSenderIdentity($identity) {
        $identity = strtoupper(trim($identity));
        $identities = $this->getSenderIdentities();
        $found = false;
        foreach ($identities as &$id) {
            if ($id['identity'] === $identity) {
                $id['is_default'] = true;
                $found = true;
            } else {
                $id['is_default'] = false;
            }
        }
        if (!$found) return ['success' => false, 'message' => 'Sender identity not found'];
        $this->saveSenderIdentities($identities);
        return ['success' => true, 'message' => 'Default sender identity set'];
    }

    /**
     * Save sender identities to sms_config
     */
    private function saveSenderIdentities($identities) {
        require __DIR__ . '/../includes/db_connection.php';
        $json = json_encode($identities);
        $stmt = $pdo->prepare("REPLACE INTO sms_config (config_key, config_value) VALUES ('sender_identities', ?)");
        $stmt->execute([$json]);
    }

    /**
     * Get approved sender identities (for modal, etc.)
     */
    public function getApprovedSenderIdentities() {
        return $this->getSenderIdentities();
    }

    /**
     * Get default sender identity
     */
    public function getDefaultSenderIdentity() {
        $identities = $this->getSenderIdentities();
        foreach ($identities as $id) {
            if (!empty($id['is_default'])) return $id['identity'];
        }
        return $identities[0]['identity'] ?? 'HRMS';
    }
}
?>
