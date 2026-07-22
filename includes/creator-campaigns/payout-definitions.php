<?php
declare(strict_types=1);
function mg_creator_campaign_payout_required_tables():array{return['creator_campaign_payout_profiles','creator_campaign_payouts','creator_campaign_payout_items','creator_campaign_payout_events','creator_campaign_disputes','creator_campaign_dispute_events'];}
function mg_creator_campaign_payout_installed(PDO $pdo):bool{$t=mg_creator_campaign_payout_required_tables();$p=implode(',',array_fill(0,count($t),'?'));$s=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ({$p})");$s->execute($t);return(int)$s->fetchColumn()===count($t);}
function mg_creator_campaign_payout_transitions():array{return['draft'=>['approved','cancelled'],'approved'=>['processing','cancelled'],'processing'=>['paid','failed'],'failed'=>['processing','cancelled'],'paid'=>['reversed'],'cancelled'=>[],'reversed'=>[]];}
function mg_creator_campaign_payout_assert_transition(string $from,string $to):void{if(!in_array($to,mg_creator_campaign_payout_transitions()[$from]??[],true))throw new DomainException("Invalid payout transition from {$from} to {$to}.");}
