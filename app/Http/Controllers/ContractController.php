<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use DocuSign\eSign\Configuration;
use DocuSign\eSign\Api\EnvelopesApi;
use DocuSign\eSign\Client\ApiClient;
use DocuSign\eSign\Client\ApiException;
use DocuSign\eSign\Model\Document;
use DocuSign\eSign\Model\RecipientViewRequest;
use DocuSign\eSign\Model\SignHere;
use DocuSign\eSign\Model\DateSigned;
use DocuSign\eSign\Model\FullName;
use DocuSign\eSign\Model\Tabs;
use DocuSign\eSign\Model\Signer;
use DocuSign\eSign\Model\Recipients;
use DocuSign\eSign\Model\InlineTemplate;
use DocuSign\eSign\Model\CompositeTemplate;
use DocuSign\eSign\Model\EnvelopeDefinition;
use DocuSign\eSign\Model\Text;
use DocuSign\eSign\Model\TextCustomField;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Filesystem\Filesystem;

class ContractController extends Controller
{
    //
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|min:3|max:50',
            'lastname' => 'required|min:3|max:50',
            'email' => 'required|email|max:255',
            'token' => 'required'
        ]);        
        if ($validator->fails() || $request->token != 'jbXZu1a2jbWkHWy7H8TFIvSOcCTG1ZrFLkOQ0KpkTOWU5P88k2q0UvKzjHP3rtI8') {
            return redirect('/contract')
            ->withErrors($validator)
            ->withInput();
        }else{
            $args['envelope_args']['signer_email'] = $request->email;
            $args['envelope_args']['signer_name'] = $request->name . ' ' . $request->lastname;
            $args['envelope_args']['signer_client_id'] = 1000;
            $args['envelope_args']['ds_return_url'] = 'https://veteranwatchdog.kinsta.cloud';
            $args['envelope_args']['cc_email'] = "clientcare@veteranwatchdog.com";
            $args['envelope_args']['cc_name'] = "Client Care";
            $args['envelope_args']['status'] = "sent";
        }
        return $this->send($request, $args);
    }

    public function send(Request $request, $args){
        $apiClient = new ApiClient();
        $apiClient->getOAuth()->setOAuthBasePath(env('DS_AUTH_SERVER')); 
        try{
            $accessToken = $this->getToken($apiClient);
        } catch (\Throwable $th) {
            return $th->getMessage();
        }

        $userInfo = $apiClient->getUserInfo($accessToken);
        $accountInfo = $userInfo[0]->getAccounts();
        $apiClient->getConfig()->setHost($accountInfo[0]->getBaseUri() . env('DS_ESIGN_URI_SUFFIX'));
        $envelopeDefenition = $this->buildEnvelope($request, $args);

        try {       
            $envelopeApi = new EnvelopesApi($apiClient);
            $result = $envelopeApi->createEnvelope($accountInfo[0]->getAccountId(), $envelopeDefenition);
            } catch (\Throwable $th) {
               return $th->getMessage();
       }  

       $envelope_id = $result->getEnvelopeId();
    
        $authentication_method = 'None'; # How is this application authenticating
        # the signer? See the `authentication_method' definition
        # https://developers.docusign.com/docs/esign-rest-api/reference/envelopes/envelopeviews/createrecipient/

        $recipient_view_request = new RecipientViewRequest();
		$recipient_view_request->setReturnUrl($args['envelope_args']['ds_return_url']);
		$recipient_view_request->setClientUserId($args['envelope_args']['signer_client_id']);
		$recipient_view_request->setAuthenticationMethod("None");
		$recipient_view_request->setUserName($request['name']);
		$recipient_view_request->setEmail($request['email']);

        $viewUrl = $envelopeApi->createRecipientView($accountInfo[0]->getAccountId(), $envelope_id, $recipient_view_request);
        return $viewUrl->getUrl();
    }

    public function buildEnvelope(Request $request, $args){

        $pdf = Storage::disk('public')->get('/assets/World_Wide_Corp_Battle_Plan_Trafalgar.docx');
        $pdfName = File::name(public_path('/assets/World_Wide_Corp_Battle_Plan_Trafalgar.docx'));
        $infoExt = pathinfo(public_path('/assets/World_Wide_Corp_Battle_Plan_Trafalgar.docx'));
        $fileContent = $pdf;
        $fileName = $pdfName;
        $fileExtension = $infoExt['extension'];
        $recipientEmail = $request['email'];
        $recipientName = $request['name'];            

        $document = new Document([
            'document_id' => "1",
            'document_base64' => base64_encode($fileContent),
            'file_extension' => $fileExtension,  
            'name' => $fileName 
            ]);
        $sign_here_tab = new SignHere([
            'anchor_string' => "/sn1/",  
            'anchor_units' => "pixels",  
            'anchor_x_offset' => "20",  
            'anchor_y_offset' => "0",
            "anchor_ignore_if_not_present" => "false"
            ]);
        $sign_here_tabs = [$sign_here_tab];
        $text_legal = new Text([
            'anchor_string' => '/legal/', 
            'anchor_units' => 'pixels',
            'anchor_x_offset' => '0',
            'anchor_y_offset' => '0',
            'font' => "helvetica", 'font_size' => "size11",
            'bold' => 'true', 'value' => $args['envelope_args']['signer_name'],
            'locked' => 'false', 'tab_id' => 'legal_name',
            'tab_label' => 'Legal name'
        ]);
        $sign_date = new Text([
            'anchor_string' => '/date/', 
            'anchor_units' => 'pixels',
            'anchor_x_offset' => '0',
            'anchor_y_offset' => '0',
            'font' => "helvetica", 'font_size' => "size11",
            'bold' => 'true', 'value' => date("m/d/Y"),
            'locked' => 'false', 'tab_id' => 'legal_name',
            'tab_label' => 'Legal name'
        ]);
        $text_tabs = [$text_legal, $sign_date];
        $tabs1 = new Tabs([
            'sign_here_tabs' => $sign_here_tabs,
            'text_tabs' => $text_tabs,
        ]);
        $signer = new Signer([
            'email' => $recipientEmail,
            'name' =>  $recipientName,
            'recipient_id' => "1",
            'routing_order' => "1",
            'client_user_id' => $args['envelope_args']['signer_client_id'],
            'tabs' => $tabs1 
            ]);
        $signers = [$signer];
        $recipients = new Recipients([
            'signers' => $signers 
            ]);
        $inline_template = new InlineTemplate([
            'recipients' => $recipients,  
            'sequence' => "1" 
            ]);
        $inline_templates = [$inline_template];
        $composite_template = new CompositeTemplate([
            'composite_template_id' => "1",  
            'document' => $document,  
            'inline_templates' => $inline_templates 
            ]);
        $composite_templates = [$composite_template];
        $envelope_definition = new EnvelopeDefinition([
            'composite_templates' => $composite_templates,  
            'email_subject' => "Please sign document from Veteran Watchdog",  
            'status' => "sent" 
            ]);

        return $envelope_definition;

    }

    public function getToken(ApiClient $apiClient) : string{
        try {
            $privateKey = file_get_contents(storage_path(env('DS_KEY_PATH')),true);
            $response = $apiClient->requestJWTUserToken(
                $ikey = env('DS_CLIENT_ID'),
                $userId = env('DS_IMPERSONATED_USER_ID'),
                $key = $privateKey,
                $scope = env('DS_JWT_SCOPE')
            );        
            $token = $response[0];
            $accessToken = $token->getAccessToken();
        } catch (\Throwable $th) {
            throw $th;
        }    
        return $accessToken;        
    }
}
