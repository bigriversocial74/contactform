import test from "node:test";
import assert from "node:assert/strict";
import { once } from "node:events";

import { InMemoryInvocationReceiptSink,createInternalMcpApp,hashBearerToken } from "../dist/index.js";

const token="creator-campaign-v13c-test-token";
const scopes=[
  "creator_campaigns:publish","creator_campaign_participants:manage","creator_campaign_agreements:manage",
  "creator_campaign_submissions:review","creator_campaign_attribution:manage","creator_campaign_earnings:manage",
  "creator_campaign_payouts:manage","creator_campaign_disputes:manage",
];
const tools=[
  "microgifter.creator_campaigns.publish","microgifter.creator_campaigns.schedule","microgifter.creator_campaigns.pause",
  "microgifter.creator_campaigns.resume","microgifter.creator_campaigns.complete","microgifter.creator_campaigns.cancel",
  "microgifter.creator_campaigns.application.approve","microgifter.creator_campaigns.application.decline",
  "microgifter.creator_campaigns.invitation.send","microgifter.creator_campaigns.agreement.offer",
  "microgifter.creator_campaigns.participant.suspend","microgifter.creator_campaigns.participant.remove",
  "microgifter.creator_campaigns.submission.approve","microgifter.creator_campaigns.submission.request_revision",
  "microgifter.creator_campaigns.submission.reject","microgifter.creator_campaigns.attribution.override",
  "microgifter.creator_campaigns.earning.approve","microgifter.creator_campaigns.earning.hold",
  "microgifter.creator_campaigns.earning.reject","microgifter.creator_campaigns.earning.reverse",
  "microgifter.creator_campaigns.payout.record","microgifter.creator_campaigns.dispute.resolve",
];
function config(granted=scopes,maximumOperationClass="approval_gated"){
  return {platformEnabled:true,internalHttpEnabled:true,host:"127.0.0.1",port:0,allowedOrigins:["https://internal.microgifter.test"],allowedHosts:[],tokenSha256:hashBearerToken(token),rateLimitRequests:100,rateLimitWindowMs:60000,connection:{connectionId:"cc-action-connection",clientKey:"cc-action-client",userId:"merchant-user",workspace:{type:"merchant",id:"merchant-workspace"},scopes:granted,maximumOperationClass,tokenVersion:1},bridge:{enabled:false,url:"",secret:"",timeoutMs:8000}};
}
async function withServer(configuration,bridge,callback){const receipts=new InMemoryInvocationReceiptSink();const app=createInternalMcpApp(configuration,receipts,bridge);const server=app.listen(0,"127.0.0.1");await once(server,"listening");const address=server.address();assert.equal(typeof address,"object");try{await callback({baseUrl:`http://127.0.0.1:${address.port}`,receipts});}finally{server.close();await once(server,"close");}}
async function rpc(baseUrl,body){return fetch(`${baseUrl}/mcp`,{method:"POST",headers:{"content-type":"application/json",accept:"application/json, text/event-stream",authorization:`Bearer ${token}`,origin:"https://internal.microgifter.test"},body:JSON.stringify(body)});}

test("Phase 13C exposes exactly 22 scope-filtered request tools",async()=>{
  const bridge={requestCreatorCampaignAction:async()=>({id:"action-id",status:"waiting_for_approval"})};
  await withServer(config(),bridge,async({baseUrl})=>{const response=await rpc(baseUrl,{jsonrpc:"2.0",id:1,method:"tools/list",params:{}});const payload=await response.json();assert.deepEqual(payload.result.tools.map(tool=>tool.name),tools);for(const tool of payload.result.tools){assert.equal(tool.annotations.readOnlyHint,false);assert.equal(tool.annotations.destructiveHint,false);assert.equal(tool.annotations.idempotentHint,true);assert.equal(tool.annotations.openWorldHint,false);}});
  await withServer(config(["creator_campaign_payouts:manage"]),bridge,async({baseUrl})=>{const response=await rpc(baseUrl,{jsonrpc:"2.0",id:2,method:"tools/list",params:{}});const payload=await response.json();assert.deepEqual(payload.result.tools.map(tool=>tool.name),["microgifter.creator_campaigns.payout.record"]);});
  await withServer(config(["creator_campaigns:publish"],"draft"),bridge,async({baseUrl})=>{const response=await rpc(baseUrl,{jsonrpc:"2.0",id:3,method:"tools/list",params:{}});const payload=await response.json();assert.deepEqual(payload.result.tools.map(tool=>tool.name),[]);});
});

test("Phase 13C tools create owner requests and never execute canonical actions",async()=>{
  const calls=[];const bridge={requestCreatorCampaignAction:async(connectionId,toolName,input)=>{calls.push({connectionId,toolName,input});return {id:"requested-action",status:"waiting_for_approval",approval:{status:"pending"}};}};
  await withServer(config(["creator_campaigns:publish"]),bridge,async({baseUrl,receipts})=>{
    const response=await rpc(baseUrl,{jsonrpc:"2.0",id:4,method:"tools/call",params:{name:"microgifter.creator_campaigns.publish",arguments:{grant_id:"11111111-1111-4111-8111-111111111111",campaign_id:"cc_1234567890abcdef",expected_lock_version:3,reason:"Campaign is ready for publication.",requested_reason:"Merchant requested launch preparation.",idempotency_key:"cc13c-publish-001"}}});const payload=await response.json();
    assert.equal(payload.result.structuredContent.ok,true);assert.equal(payload.result.structuredContent.execution.performed,false);assert.equal(payload.result.structuredContent.execution.status,"waiting_for_owner_approval");assert.equal(calls.length,1);assert.equal(calls[0].connectionId,"cc-action-connection");assert.equal(calls[0].toolName,"microgifter.creator_campaigns.publish");assert.equal(calls[0].input.campaign_id,"cc_1234567890abcdef");assert.equal(receipts.all().length,1);assert.equal(receipts.all()[0].operationClass,"approval_gated");assert.equal(receipts.all()[0].requiredScope,"creator_campaigns:publish");assert.equal(receipts.all()[0].resultStatus,"success");
  });
});

test("Phase 13C fails closed when the action request bridge is disabled",async()=>{
  await withServer(config(["creator_campaigns:publish"]),undefined,async({baseUrl,receipts})=>{const response=await rpc(baseUrl,{jsonrpc:"2.0",id:5,method:"tools/call",params:{name:"microgifter.creator_campaigns.publish",arguments:{grant_id:"11111111-1111-4111-8111-111111111111",campaign_id:"cc_1234567890abcdef",expected_lock_version:3,reason:"Ready.",requested_reason:"Owner review required.",idempotency_key:"cc13c-disabled-001"}}});const payload=await response.json();assert.equal(payload.result.isError,true);assert.match(payload.result.content[0].text,/MICROGIFTER_TOOL_DISABLED/);assert.equal(receipts.all()[0].resultStatus,"failed");});
});
