<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/includes/mcp-creator-campaign-actions.php';

function mg_mcp_creator_campaign_action_bridge_authenticate(PDO $pdo,string $rawBody,array $payload): array
{
    $context=mg_mcp_draft_bridge_authenticate($pdo,$rawBody,$payload);
    if((string)($context['maximum_operation_class']??'')!=='approval_gated'||(string)($context['client_maximum_operation_class']??'')!=='approval_gated'){
        throw new MgMcpBridgeException('Connection is not authorized for approval-gated actions.',403,'MCP_CREATOR_CAMPAIGN_ACTION_OPERATION_DENIED');
    }
    return $context;
}

function mg_mcp_creator_campaign_action_bridge_dispatch(PDO $pdo,array $context,string $operation,array $arguments): array
{
    try{
        if($operation!=='creator_campaign_actions.request')throw new MgMcpBridgeException('Creator Campaign action bridge operation is not allowed.',404,'MCP_CREATOR_CAMPAIGN_ACTION_OPERATION_UNKNOWN');
        $tool=mg_mcp_bridge_text($arguments['tool_name']??'',160,'tool_name');
        $input=is_array($arguments['input']??null)?$arguments['input']:[];
        return mg_mcp_creator_campaign_action_request($pdo,$context,$tool,$input);
    }catch(MgMcpCreatorCampaignActionException $error){
        throw new MgMcpBridgeException($error->getMessage(),$error->httpStatus(),$error->errorCode());
    }catch(MgMcpAutomationGrantException $error){
        throw new MgMcpBridgeException($error->getMessage(),$error->httpStatus(),$error->errorCode());
    }
}
