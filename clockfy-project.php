<?php
if(!is_file(__DIR__.'/.env')){
    die();
}

$conf = json_decode(file_get_contents(__DIR__.'/.env'));



$data = date("Y-m-d");//,strtotime('-1 days'));

$dir = __DIR__.DIRECTORY_SEPARATOR.'apontamentos'.DIRECTORY_SEPARATOR.date("Y").DIRECTORY_SEPARATOR.date("m").DIRECTORY_SEPARATOR.date("d");

$fileApont = $dir.DIRECTORY_SEPARATOR.'apontamentos.dat';

if(!is_dir($dir)){
    mkdir($dir, 0777, true);
}
if(!is_file($fileApont)){
    file_put_contents(
        $fileApont,
        serialize([])
    );
}
$apontamentos = unserialize(file_get_contents($fileApont));

$curl = curl_init();

$filter = [
    "startDate" => $data."T00:00:00.000Z",
    "endDate" => $data."T23:59:59.999Z",
    "me" => "TEAM",
    "userGroupIds" => [],
    "userIds" => [],
    "projectIds" => [],
    "clientIds" => [],
    "taskIds" => [],
    "tagIds" => [],
    "billable" => "BOTH",
    "includeTimeEntries" => "true",
    "zoomLevel" => "week",
    "description" => "",
    "archived" => "All",
    "roundingOn" => "false"
];

curl_setopt_array($curl, array(
  CURLOPT_URL => $conf->urlClockify,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => json_encode($filter),
  CURLOPT_HTTPHEADER => array(
    "Content-Type: application/json",
    "Postman-Token: 8343e20a-ffd2-48a2-bb67-a1d0cfeaa7d2",
    "X-Api-Key: XAedB7B5hyPg/ubo",
    "cache-control: no-cache"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  $dados = json_decode($response);
}

foreach($dados->timeEntries as $timeEntrie){
    
    $userId = $timeEntrie->user->id;

    if(isset($conf->usersMap->$userId)){

        if(!in_array($timeEntrie->id,$apontamentos)){

            $user = $conf->usersMap->$userId;

            if($user->projectToken !== ''){
                
                $matches = [];
                if(preg_match (  "([#][0-9]+)" , $timeEntrie->description , $matches)){
                    $workPackage = str_replace('#','',$matches[0]);

                    $dataProject = [
                        "_links"=> [
                        "workPackage"=> [
                            "href"=> "/api/v3/work_packages/$workPackage"
                        ]
                        ],
                        "hours"=> $timeEntrie->timeInterval->duration,
                        "comment"=> ltrim(substr($timeEntrie->description,strrpos($timeEntrie->description, $workPackage) + strlen($workPackage) + 1, 255)),
                        "spentOn"=> $data
                    ];

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                    CURLOPT_URL => $conf->urlProject,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => json_encode($dataProject),
                    CURLOPT_HTTPHEADER => array(
                            "Authorization: Basic ". base64_encode('apikey:'.$user->projectToken ) ,
                            "Content-Type: application/json",
                            "cache-control: no-cache"
                        ),
                    ));

                    $response = curl_exec($curl);
                    $err = curl_error($curl);

                    curl_close($curl);

                    if ($err) {
                        echo "cURL Error #:" . $err;
                    } else {
                        $response = json_decode($response);
                        if($response->_type == 'TimeEntry'){
                            $apontamentos[] = $timeEntrie->id;
                        }
                    }
                }
            }
        }
    }else{
        $matches = [];
        if(@preg_match ("([#]ptoken)" , $timeEntrie->description , $matches)){
            $tokenProject = str_replace('#ptoken ','',$timeEntrie->description);
            $conf->usersMap->$userId = $timeEntrie->user;
            $conf->usersMap->$userId->projectToken = $tokenProject;            
        }else{
            //echo $timeEntrie->description."\n";
        }
    }
}
file_put_contents(
    $fileApont,
    serialize($apontamentos)
);
file_put_contents('.env',json_encode($conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));