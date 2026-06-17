<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/includes/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function mg_onboarding_json(array $payload,int $status=200): never
{
    http_response_code($status);
    echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}

function mg_onboarding_public_ip(string $ip): bool
{
    return filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)!==false;
}

function mg_onboarding_validate_url(string $input): array
{
    $input=trim($input);
    if($input==='')throw new InvalidArgumentException('Enter a business website.');
    if(!preg_match('#^https?://#i',$input))$input='https://'.$input;
    $parts=parse_url($input);
    if($parts===false||!isset($parts['scheme'],$parts['host']))throw new InvalidArgumentException('Enter a valid public website URL.');
    $scheme=strtolower((string)$parts['scheme']);
    if(!in_array($scheme,['http','https'],true))throw new InvalidArgumentException('Only HTTP and HTTPS websites can be scanned.');
    $host=strtolower(rtrim((string)$parts['host'],'.'));
    if($host===''||$host==='localhost'||str_ends_with($host,'.local'))throw new InvalidArgumentException('Enter a public website hostname.');
    $records=dns_get_record($host,DNS_A|DNS_AAAA);
    if(!is_array($records)||$records===[])throw new InvalidArgumentException('The website hostname could not be resolved.');
    foreach($records as $record){$ip=(string)($record['ip']??$record['ipv6']??'');if($ip===''||!mg_onboarding_public_ip($ip))throw new InvalidArgumentException('Private or restricted network addresses cannot be scanned.');}
    $path=(string)($parts['path']??'/');
    $query=isset($parts['query'])?'?'.$parts['query']:'';
    return ['url'=>$scheme.'://'.$host.$path.$query,'host'=>$host];
}

function mg_onboarding_fetch(string $url,string $expectedHost): string
{
    if(!function_exists('curl_init'))throw new RuntimeException('Website scanning is temporarily unavailable.');
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>9,CURLOPT_MAXREDIRS=>0,CURLOPT_USERAGENT=>'Microgifter-Onboarding/1.0',CURLOPT_HTTPHEADER=>['Accept: text/html,application/xhtml+xml'],CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_MAXFILESIZE=>1048576]);
    $body=curl_exec($ch);
    $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
    $type=strtolower((string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE));
    $effective=(string)curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);
    $error=curl_error($ch);
    curl_close($ch);
    if(!is_string($body)||$body==='')throw new RuntimeException($error!==''?'The website could not be read.':'The website returned no readable content.');
    if($status<200||$status>=400)throw new RuntimeException('The website did not return a readable page.');
    if($type!==''&&!str_contains($type,'text/html')&&!str_contains($type,'application/xhtml'))throw new RuntimeException('The website did not return an HTML page.');
    $effectiveParts=parse_url($effective);
    if($effectiveParts===false||strtolower((string)($effectiveParts['host']??''))!==$expectedHost)throw new RuntimeException('Cross-domain redirects are not scanned.');
    return substr($body,0,1048576);
}

function mg_onboarding_extract(string $html): array
{
    libxml_use_internal_errors(true);
    $dom=new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html,LIBXML_NOERROR|LIBXML_NOWARNING|LIBXML_NONET);
    $xpath=new DOMXPath($dom);
    foreach(['//script','//style','//noscript','//svg'] as $query){foreach($xpath->query($query)?:[] as $node){$node->parentNode?->removeChild($node);}}
    $title=trim((string)($xpath->query('//title')->item(0)?->textContent??''));
    $description='';
    foreach($xpath->query('//meta[@name or @property]')?:[] as $meta){$key=strtolower((string)($meta->attributes?->getNamedItem('name')?->nodeValue??$meta->attributes?->getNamedItem('property')?->nodeValue??''));if(in_array($key,['description','og:description'],true)){$description=trim((string)($meta->attributes?->getNamedItem('content')?->nodeValue??''));if($description!=='')break;}}
    $headings=[];foreach($xpath->query('//h1|//h2|//h3')?:[] as $node){$text=trim(preg_replace('/\s+/',' ',(string)$node->textContent)??'');if($text!==''&&!in_array($text,$headings,true))$headings[]=$text;if(count($headings)>=12)break;}
    $body=trim(preg_replace('/\s+/',' ',(string)($xpath->query('//body')->item(0)?->textContent??''))??'');
    return ['title'=>mb_substr($title,0,180),'description'=>mb_substr($description,0,400),'headings'=>$headings,'text'=>mb_substr($body,0,5000)];
}

function mg_onboarding_ideas(array $content,string $businessName): array
{
    $haystack=mb_strtolower(implode(' ',[$content['title'],$content['description'],implode(' ',$content['headings']),$content['text']]));
    $categories=[
        'food'=>['coffee','restaurant','cafe','bakery','food','pizza','bar','kitchen','dining','tea'],
        'service'=>['salon','spa','repair','cleaning','consulting','service','appointment','studio','fitness','gym'],
        'retail'=>['shop','store','boutique','clothing','jewelry','gift','retail','product'],
        'experience'=>['tour','class','workshop','event','experience','lesson','ticket','adventure'],
    ];
    $category='general';foreach($categories as $name=>$keywords){foreach($keywords as $keyword){if(str_contains($haystack,$keyword)){$category=$name;break 2;}}}
    $name=$businessName!==''?$businessName:($content['title']!==''?$content['title']:'Your business');
    $sets=[
        'food'=>[
            [$name.' prepaid tasting','Pre-sell a signature tasting, meal, or beverage experience redeemable during selected hours.','$35'],
            ['Coffee, lunch, or dinner for two','Package a simple two-person visit that customers can buy now and schedule later.','$50'],
            ['Local loyalty bundle','Sell several future visits together with a small bonus that encourages repeat business.','$75'],
        ],
        'service'=>[
            [$name.' service credit','Sell flexible credit now for a future appointment or service visit.','$50'],
            ['Priority appointment package','Pre-sell a premium appointment with preferred scheduling or an added bonus.','$100'],
            ['Three-visit service bundle','Create predictable future demand through a discounted multi-visit package.','$150'],
        ],
        'retail'=>[
            [$name.' future purchase credit','Sell store credit now with a limited bonus for a later purchase.','$50'],
            ['New-product reservation','Let customers reserve an upcoming product before inventory arrives.','$75'],
            ['Curated local gift bundle','Package several products into a prepaid gift or seasonal collection.','$100'],
        ],
        'experience'=>[
            [$name.' early-access pass','Pre-sell access to an upcoming class, event, workshop, or experience.','$40'],
            ['Experience for two','Create a simple two-person package customers can purchase as a gift.','$80'],
            ['Season pass or class bundle','Pre-sell multiple future experiences and reward early commitment.','$150'],
        ],
        'general'=>[
            [$name.' prepaid credit','Sell flexible future-use credit with clear redemption terms.','$50'],
            ['Limited founder offer','Create a limited early-buyer package with a useful bonus.','$75'],
            ['Three-visit customer bundle','Turn one purchase into several scheduled future visits.','$100'],
        ],
    ];
    return array_map(static fn(array $idea): array=>['title'=>$idea[0],'description'=>$idea[1],'value'=>$idea[2]],$sets[$category]);
}

if(strtoupper($_SERVER['REQUEST_METHOD']??'GET')!=='POST')mg_onboarding_json(['ok'=>false,'error'=>'Method not allowed.'],405);
$csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
if(!mg_verify_csrf(is_string($csrf)?$csrf:null))mg_onboarding_json(['ok'=>false,'error'=>'Your session expired. Refresh the page and try again.'],419);
$last=(int)($_SESSION['mg_public_website_scan_at']??0);
if($last>0&&time()-$last<4)mg_onboarding_json(['ok'=>false,'error'=>'Please wait a few seconds before scanning another website.'],429);
$_SESSION['mg_public_website_scan_at']=time();
$raw=file_get_contents('php://input');$input=json_decode(is_string($raw)?$raw:'',true);
if(!is_array($input))mg_onboarding_json(['ok'=>false,'error'=>'Invalid request.'],400);
try{
    $validated=mg_onboarding_validate_url((string)($input['website']??''));
    $businessName=trim(mb_substr((string)($input['business_name']??''),0,160));
    $html=mg_onboarding_fetch($validated['url'],$validated['host']);
    $content=mg_onboarding_extract($html);
    if($businessName===''&&$content['title']!=='')$businessName=preg_replace('/\s*[|–—-].*$/u','',$content['title'])??$content['title'];
    mg_onboarding_json(['ok'=>true,'data'=>['website'=>$validated['url'],'business_name'=>$businessName,'page'=>['title'=>$content['title'],'description'=>$content['description'],'headings'=>array_slice($content['headings'],0,6)],'ideas'=>mg_onboarding_ideas($content,$businessName)]]);
}catch(InvalidArgumentException $error){mg_onboarding_json(['ok'=>false,'error'=>$error->getMessage()],422);}catch(Throwable $error){error_log('[agentic-onboarding-scan] '.$error->getMessage());mg_onboarding_json(['ok'=>false,'error'=>'We could not scan that website. Starter ideas are available instead.'],502);}