<?php
require_once dirname(__FILE__) . '/../library/include.php';
require_once dirname(__FILE__) . '/../library/api/paymentmethods/paymentmethod.php';

class IdinController
{
    private $logger;

    public function __construct()
    {
        $this->logger = new BuckarooLogger(BuckarooLogger::INFO, 'idin');
    }

    public function returnHandler()
    {
        $this->logger->logInfo(__METHOD__ . "|1|", $_POST);

        $response = new BuckarooResponseDefault($_POST);

        if ($response && $response->isValid() && $response->hasSucceeded()) {
            $this->logger->logInfo(__METHOD__ . "|5|");
            BuckarooIdin::setCurrentUserIsVerified();
            wc_add_notice(__('You have been verified successfully', 'wc-buckaroo-bpe-gateway'), 'success');
        } else {
            $this->logger->logInfo(__METHOD__ . "|10|");
            wc_add_notice(
                empty($response->statusmessage) ?
                    __('Verification has been failed', 'wc-buckaroo-bpe-gateway') : stripslashes($response->statusmessage),
                'error');
            //var_dump($response);die();
        }

        if (!empty($_REQUEST['bk_redirect'])) {
            $this->logger->logInfo(__METHOD__ . "|15|");
            wp_safe_redirect($_REQUEST['bk_redirect']);
        }
        //var_dump($_REQUEST);die();
    }

    public function identify()
    {
        $this->logger->logInfo(__METHOD__ . "|1|");

        //var_dump(get_current_user_id());die();
        if (!BuckarooConfig::isIdin()) {
            return $this->sendError('iDIN is disabled');
        }

        $data = [];
        $data['currency']         = 'EUR';
        $data['amountDebit']      = 0;
        $data['amountCredit']     = 0;
        /*
        $data['invoice']          = $this->invoiceId;
        $data['order']            = $this->orderId;
        $data['description']      = preg_replace('/\{invoicenumber\}/', $this->invoiceId, $this->description);
        $data['channel']          = $this->channel;
        */
        $data['mode'] = BuckarooConfig::getIdinMode();
        $url = parse_url($_SERVER['HTTP_REFERER']);
        $data['returnUrl'] = $url['scheme'] . '://' . $url['host'] . '/' . $url[' path'] .
            '?wc-api=WC_Gateway_Buckaroo_idin-return&bk_redirect='.urlencode($_SERVER['HTTP_REFERER']);
        $data['ContinueOnIncomplete'] = 1;

        $idealEmulation = false;

        if ($idealEmulation) {
            $data['services']['ideal']['action']  = 'pay';
            $data['services']['ideal']['version'] = '2';
            $data['customVars']['ideal']['issuer'] = 'ABNANL2A';
            $data['amountDebit'] = 10;
            $data['invoice']          = 'emu_' . rand(1,100000000);
        } else {
            $data['services']['idin']['action']  = 'verify';
            $data['services']['idin']['version'] = '0';
            $data['customVars']['idin']['issuerId'] =
                (isset($_GET['issuer']) && BuckarooIdin::checkIfValidIssuer($_GET['issuer'])) ? $_GET['issuer'] : '';
        }

        //var_dump($data);die();

        $soap = new BuckarooSoap($data);

        if ($idealEmulation) {
            $response = BuckarooResponseFactory::getResponse($soap->transactionRequest());
        } else {
            $response = BuckarooResponseFactory::getResponse($soap->transactionRequest('DataRequest'));
        }

        $this->logger->logInfo(__METHOD__ . "|5|", $response);
        //var_dump($response);die();

        $processedResponse = fn_buckaroo_process_response(null, $response);

        $this->logger->logInfo(__METHOD__ . "|10|", $processedResponse);

        //var_dump("============5", $processedResponse); die();

        echo json_encode($processedResponse, JSON_PRETTY_PRINT);
        exit;
    }

    public function reset()
    {
        if (!BuckarooConfig::isIdin()) {
            return $this->sendError('iDIN is disabled');
        }

        BuckarooIdin::setCurrentUserIsNotVerified();

        echo 'ok';
        exit;
    }

    private function sendError($error)
    {
        echo json_encode([
            'error' => $error,
        ], JSON_PRETTY_PRINT);
    }
}
