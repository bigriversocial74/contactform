import { createHash, randomUUID } from "node:crypto";
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import * as z from "zod/v4";
import type { CanonicalBridge } from "../bridge/canonicalBridge.js";
import { CanonicalBridgeError } from "../bridge/canonicalBridge.js";
import type { ConnectionContext } from "../contracts.js";
import type { InvocationReceipt, InvocationReceiptSink } from "../receipts.js";
import { registerCreatorCampaignActionTools } from "./creatorCampaignActions.js";
import { registerCreatorCampaignDraftTools } from "./creatorCampaignDrafts.js";
import { registerCreatorCampaignReadTools } from "./creatorCampaigns.js";
import { registerDraftTools } from "./drafts.js";

export interface ToolRegistryDependencies { readonly connection:ConnectionContext; readonly receipts:InvocationReceiptSink; readonly bridge?:CanonicalBridge; }
function hasScope(connection:ConnectionContext,scope:string):boolean{return connection.scopes.includes(scope);}
function fingerprint(value:unknown):string{return createHash("sha256").update(JSON.stringify(value??{})).digest("hex");}
function errorResult(code:string,message:string){return {isError:true as const,content:[{type:"text" as const,text:JSON.stringify({ok:false,error:{code,message}})}],structuredContent:{ok:false,error:{code,message}}};}
function bridgeError(error:unknown):CanonicalBridgeError{return error instanceof CanonicalBridgeError?error:new CanonicalBridgeError("The canonical service could not complete the request.","MCP_CANONICAL_SERVICE_FAILED",500);}
async function recordReceipt(sink:InvocationReceiptSink,receipt:InvocationReceipt):Promise<void>{await sink.record(receipt);}

export function createInternalMcpServer(dependencies:ToolRegistryDependencies):McpServer{
  const server=new McpServer({name:"microgifter-mcp",version:"0.6.0",description:"Microgifter MCP read, proposal, and owner approval-gated action-request server"},{capabilities:{tools:{listChanged:false}}});
  if(hasScope(dependencies.connection,"profile:read")){
    server.registerTool("microgifter.account.get_connection_context",{description:"Return the authenticated Microgifter connection and workspace context without private account fields.",inputSchema:{},annotations:{readOnlyHint:true,destructiveHint:false,idempotentHint:true,openWorldHint:false}},async()=>{
      const requestId=randomUUID();const startedAt=new Date().toISOString();const started=Date.now();const data={connection_id:dependencies.connection.connectionId,client_key:dependencies.connection.clientKey,user_id:dependencies.connection.userId,workspace:dependencies.connection.workspace??null,scopes:[...dependencies.connection.scopes].sort(),maximum_operation_class:dependencies.connection.maximumOperationClass,token_version:dependencies.connection.tokenVersion,expires_at:dependencies.connection.expiresAt??null};
      await recordReceipt(dependencies.receipts,{requestId,connectionId:dependencies.connection.connectionId,toolName:"microgifter.account.get_connection_context",operationClass:"read",requiredScope:"profile:read",inputFingerprint:fingerprint({}),resultStatus:"success",httpStatus:200,durationMs:Date.now()-started,recordCount:1,startedAt,completedAt:new Date().toISOString()});
      return {content:[{type:"text",text:JSON.stringify({ok:true,request_id:requestId,data})}],structuredContent:{ok:true,request_id:requestId,data}};
    });
  }
  if(hasScope(dependencies.connection,"catalog:read")){
    server.registerTool("microgifter.catalog.search",{description:"Search currently published Microgifter catalog products through canonical visibility rules.",inputSchema:{query:z.string().trim().max(100).optional(),location:z.string().trim().max(100).optional(),category:z.string().trim().max(60).optional(),limit:z.number().int().min(1).max(25).default(10),cursor:z.string().max(900).optional()},annotations:{readOnlyHint:true,destructiveHint:false,idempotentHint:true,openWorldHint:false}},async(arguments_)=>{
      if(!dependencies.bridge)return errorResult("MICROGIFTER_TOOL_DISABLED","The canonical catalog bridge is not enabled in this release.");const requestId=randomUUID();const startedAt=new Date().toISOString();const started=Date.now();
      try{const data=await dependencies.bridge.searchCatalog(dependencies.connection.connectionId,arguments_);await recordReceipt(dependencies.receipts,{requestId,connectionId:dependencies.connection.connectionId,toolName:"microgifter.catalog.search",operationClass:"read",requiredScope:"catalog:read",inputFingerprint:fingerprint(arguments_),resultStatus:"success",httpStatus:200,durationMs:Date.now()-started,recordCount:data.items.length,startedAt,completedAt:new Date().toISOString()});const value={ok:true,request_id:requestId,data};return {content:[{type:"text",text:JSON.stringify(value)}],structuredContent:value};}
      catch(error){const failure=bridgeError(error);await recordReceipt(dependencies.receipts,{requestId,connectionId:dependencies.connection.connectionId,toolName:"microgifter.catalog.search",operationClass:"read",requiredScope:"catalog:read",inputFingerprint:fingerprint(arguments_),resultStatus:failure.status===403?"denied":failure.status===422?"validation_error":"failed",httpStatus:failure.status,durationMs:Date.now()-started,recordCount:0,errorCode:failure.code,...(failure.status===403?{denialReason:failure.message}:{}),startedAt,completedAt:new Date().toISOString()});return errorResult(failure.code,failure.message);}
    });
    server.registerTool("microgifter.catalog.get_item",{description:"Return one currently published Microgifter catalog item through the canonical public projection.",inputSchema:{product_id:z.string().uuid(),slug:z.string().trim().max(190).optional()},annotations:{readOnlyHint:true,destructiveHint:false,idempotentHint:true,openWorldHint:false}},async(arguments_)=>{
      if(!dependencies.bridge)return errorResult("MICROGIFTER_TOOL_DISABLED","The canonical catalog bridge is not enabled in this release.");const requestId=randomUUID();const startedAt=new Date().toISOString();const started=Date.now();
      try{const data=await dependencies.bridge.getCatalogItem(dependencies.connection.connectionId,arguments_);await recordReceipt(dependencies.receipts,{requestId,connectionId:dependencies.connection.connectionId,toolName:"microgifter.catalog.get_item",operationClass:"read",requiredScope:"catalog:read",inputFingerprint:fingerprint(arguments_),resultStatus:"success",httpStatus:200,durationMs:Date.now()-started,recordCount:1,startedAt,completedAt:new Date().toISOString()});const value={ok:true,request_id:requestId,data};return {content:[{type:"text",text:JSON.stringify(value)}],structuredContent:value};}
      catch(error){const failure=bridgeError(error);await recordReceipt(dependencies.receipts,{requestId,connectionId:dependencies.connection.connectionId,toolName:"microgifter.catalog.get_item",operationClass:"read",requiredScope:"catalog:read",inputFingerprint:fingerprint(arguments_),resultStatus:failure.status===403?"denied":failure.status===422?"validation_error":"failed",httpStatus:failure.status,durationMs:Date.now()-started,recordCount:0,errorCode:failure.code,...(failure.status===403?{denialReason:failure.message}:{}),startedAt,completedAt:new Date().toISOString()});return errorResult(failure.code,failure.message);}
    });
  }
  registerCreatorCampaignReadTools(server,dependencies);
  registerCreatorCampaignDraftTools(server,dependencies);
  registerCreatorCampaignActionTools(server,dependencies);
  registerDraftTools(server,dependencies);
  return server;
}
