<?php

namespace Statbus\Controllers;

use Psr\Container\ContainerInterface;
use Statbus\Controllers\Controller as Controller;
use Statbus\Models\Player as Player;
use Statbus\Controllers\MessageController as MessageController;

class StatbusController extends Controller {


  public function __construct(ContainerInterface $container) {
    parent::__construct($container);
    $this->guzzle = $this->container->get('guzzle');
    $this->user = $this->container->get('user');
    $this->sb = $this->container->get('settings')['statbus'];
  }

  public function index($request, $response, $args) {
    return $this->view->render($response, 'index.tpl',[
      'numbers' => $this->getBigNumbers(),
      'poly'    => $this->getPolyLine(),
    ]);
  }

  public function getBigNumbers(){
    $numbers = new \stdclass;
    $numbers->playtime = number_format($this->DB->row("SELECT sum(tbl_role_time.minutes) AS minutes FROM tbl_role_time WHERE tbl_role_time.job = 'Living';")->minutes);
    $numbers->deaths = number_format($this->DB->cell("SELECT count(id) as deaths FROM tbl_death;")+rand(-15,15));//fuzzed
    $numbers->rounds = number_format($this->DB->cell("SELECT count(id) as rounds FROM tbl_round;"));
    $numbers->books = number_format($this->DB->cell("SELECT count(tbl_library.id) FROM tbl_library WHERE tbl_library.content != ''
      AND (tbl_library.deleted IS NULL OR tbl_library.deleted = 0)"));
    return $numbers;
  }

  public function doAdminsPlay($request, $response, $args){
    $args = $request->getQueryParams();
    $maxRange = 30;
    if($this->user->canAccessTGDB){
      $maxRange = 90;
    }
    if(isset($args['interval'])) {
      $options = array(
        'options'=>array(
        'default'=>20,
        'min_range'=>2,
        'max_range'=>$maxRange
      ));
      $interval = filter_var($args['interval'], FILTER_VALIDATE_INT, $options);
    } else {
      $interval = 20;
    }
    $admins = $this->DB->run("SELECT A.ckey,
      A.rank,
      A.feedback,
      R.flags,
      R.exclude_flags,
      R.can_edit_flags,
      (SELECT count(C.id) FROM tbl_connection_log AS C
      WHERE A.ckey = C.ckey AND C.datetime BETWEEN CURDATE() - INTERVAL ? DAY AND CURDATE()) AS connections,
      (SELECT sum(G.delta) FROM tbl_role_time_log AS G
      WHERE A.ckey = G.ckey AND G.job = 'Ghost' AND G.datetime BETWEEN CURDATE() - INTERVAL ? DAY AND CURDATE()) AS ghost,
      (SELECT sum(L.delta) FROM tbl_role_time_log AS L
      WHERE A.ckey = L.ckey
      AND L.job = 'Living'
      AND L.datetime BETWEEN CURDATE() - INTERVAL ? DAY AND CURDATE()
      ) AS living
      FROM tbl_admin as A
      LEFT JOIN tbl_admin_ranks AS R ON A.rank = R.rank
      GROUP BY A.ckey;", $interval, $interval, $interval);
    $perms = $this->container->get('settings')['statbus']['perm_flags'];

    $pm = new Player($this->container->get('settings')['statbus']);
    foreach ($admins as &$a){
      foreach($perms as $p => $b){
        if ($a->flags & $b){
          $a->permissions[] = $p;
        }
      }
      $a->total = $a->ghost + $a->living;
      if(isset($args['json'])) continue;
      $a = $pm->parsePlayer($a);
    }
    $format = $request->getQueryParam('format');
    if('wiki' === $format) {
      $return = '';
      foreach ($admins as $a){
        $return.= "{{Admin<br>";
        $return.= "|Name=$a->ckey<br>";
        $return.= "|Rank=$a->rank<br>";
        $return.= "|Feedback=$a->feedback";
        $return.= "}}<br>";
      }
      return $this->view->render($response, 'dump.tpl',[
        'dump' => $return,
        'wide' => true
      ]);
    }
    return $this->view->render($response, 'info/admins.tpl',[
      'admins'   => $admins,
      'interval' => $interval,
      'perms'    => $perms,
      'wide'     => true,
      'maxRange' => $maxRange
    ]);
  }

  public function adminLogs($request, $response, $args){
    if(isset($args['page'])) {
      $this->page = filter_var($args['page'], FILTER_VALIDATE_INT);
    }
    $this->pages = ceil($this->DB->cell("SELECT count(tbl_admin_log.id) FROM tbl_admin_log") / $this->per_page);
    $logs = $this->DB->run("SELECT
      L.id,
      L.datetime,
      L.adminckey,
      L.operation,
      L.target,
      L.log,
      IF(A.rank IS NULL, 'Player', A.rank) as adminrank
      FROM tbl_admin_log as L
      LEFT JOIN tbl_admin as A ON L.adminckey = A.ckey
      ORDER BY L.datetime DESC
      LIMIT ?,?", ($this->page * $this->per_page) - $this->per_page, $this->per_page);
    $pm = new Player($this->container->get('settings')['statbus']);
    foreach ($logs as &$l){
      $l->admin = new \stdclass;
      $l->admin->ckey = $l->adminckey;
      $l->admin->rank = $l->adminrank;
      $l->admin = $pm->parsePlayer($l->admin);
      $l->class = '';
      $l->icon  = 'edit';
      switch($l->operation){
        case 'add admin':
          $l->class = 'success';
          $l->icon  = 'user-plus';
        break;
        case 'remove admin':
          $l->class = 'danger';
          $l->icon  = 'user-times';
        break;
        case 'change admin rank':
          $l->class = 'info';
          $l->icon  = 'user-tag';
        break;
        case 'add rank':
          $l->class = 'success';
          $l->icon  = 'plus-square';
        break;
        case 'remove rank':
          $l->class = 'warning';
          $l->icon  = 'minus-square';
        break;
        case 'change rank flags':
          $l->class = 'primary';
          $l->icon  = 'flag';
        break;
      }
      $l->operation = ucwords($l->operation);
    }
    return $this->view->render($response, 'info/admin_log.tpl',[
      'logs'   => $logs,
      'info'   => $this,
      'wide'   => true
    ]);
  }

  public function tgdbIndex() {
    //This method exists solely to scaffold the tgdb index page
    $memos = (new MessageController($this->container))->getAdminMemos();
    return $this->view->render($this->response, 'tgdb/index.tpl',[
      'memos' => $memos
    ]);
  }

  public function getPolyLine() {
    if($this->container->get('settings')['statbus']['remote_log_src']){
      $server = pick('sybil,terry');
      try {
        $poly = $this->guzzle->request('GET','https://tgstation13.org/parsed-logs/'.$server.'/data/npc_saves/Poly.json');
        $poly = json_decode((string) $poly->getBody(), TRUE);
        return pick($poly['phrases']);
      }
      catch (\Guzzle\Http\Exception\ConnectException $e) {
        $response = json_encode((string)$e->getResponse()->getBody());
      }
    } else {
      return false;
    }
  }

  public function popGraph(){
    $query = "SELECT
    FLOOR(AVG(admincount)) AS admins,
    FLOOR(AVG(playercount)) AS `players`,
    DATE_FORMAT(`time`, '%Y-%m-%e %H:00:00') as `date`,
    count(round_id) AS rounds
    FROM tbl_legacy_population
    WHERE `time` > CURDATE() - INTERVAL 30 DAY
    GROUP BY HOUR (`time`), DAY(`TIME`), MONTH(`TIME`), YEAR(`TIME`)
    ORDER BY `time` DESC;";
    $data = $this->DB->run($query);
    return $this->view->render($this->response, 'info/heatmap.tpl',[
      'data' => json_encode($data),
      'wide' => TRUE
    ]);
  }

  public function last30Days(){
    $query = "SELECT SUM(`delta`) AS `minutes`,
      DATE_FORMAT(`datetime`, '%Y-%m-%d %H:00:00') AS `date`,
      `job`
      FROM tbl_role_time_log
      WHERE `job` IN ('Living','Ghost')
      AND `DATETIME` > CURDATE() - INTERVAL 30 DAY
      GROUP BY `job`, HOUR(`datetime`), DAY(`DATETIME`), MONTH(`DATETIME`), YEAR(`DATETIME`)
      ORDER BY `date` ASC;";
      $minutes = $this->DB->run($query);
      return $this->view->render($this->response, 'info/30days.tpl',[
        'minutes' => json_encode($minutes),
        'wide' => TRUE
      ]);
  }

  public function submitToAuditLog($action, $text){
    //Check if the audit log exists
    try {
      $this->DB->run("SELECT 1 FROM tbl_external_activity LIMIT 1");
    } catch (\PDOException $e){
      return false;
    }
    $this->DB->insert('tbl_external_activity',[
      'action' => $action,
      'text'   => $text,
      'ckey'   => ($this->user->ckey) ? $this->user->ckey : null,
      'ip'     => ip2long($_SERVER['REMOTE_ADDR'])
    ]);
  }

  public function electionManager($request, $response, $args) {
    if($request->isPost() && $this->sb['election_officer'] === $this->user->ckey){
      $update = $request->getParsedBody()['candidates'];
      $update = explode(",", $update);
      foreach($update as &$u){
        $u = strtolower(preg_replace("/[^a-zA-Z0-9]/", '', $u));
      }
      try{
        $handle = fopen(ROOTDIR."/tmp/candidates.json", 'w+');
        fwrite($handle, json_encode($update));
        fclose($handle);
      } catch(Excention $e){
        die($e->getMessage());
      }
    }
    $args = $request->getQueryParams();
    if(isset($args['interval'])) {
      $options = array(
        'options'=>array(
        'default'=>60,
        'min_range'=>2,
        'max_range'=>180
      ));
      $interval = filter_var($args['interval'], FILTER_VALIDATE_INT, $options);
    } else {
      $interval = 60;
    }
    $list = "('".implode("','",$this->sb['candidates'])."')";
    $candidates = $this->DB->run("SELECT A.ckey,
      (SELECT count(C.id) FROM tbl_connection_log AS C
      WHERE A.ckey = C.ckey AND C.datetime BETWEEN CURDATE() - INTERVAL ? DAY AND CURDATE()) AS connections,
      (SELECT sum(G.delta) FROM tbl_role_time_log AS G
      WHERE A.ckey = G.ckey AND G.job = 'Ghost' AND G.datetime BETWEEN CURDATE() - INTERVAL ? DAY AND CURDATE()) AS ghost,
      (SELECT sum(L.delta) FROM tbl_role_time_log AS L
      WHERE A.ckey = L.ckey
      AND L.job = 'Living'
      AND L.datetime BETWEEN CURDATE() - INTERVAL ? DAY AND CURDATE()
      ) AS living
      FROM tbl_player as A
      WHERE A.ckey IN $list
      GROUP BY A.ckey;", $interval, $interval, $interval);
    $pm = new Player($this->container->get('settings')['statbus']);
    foreach ($candidates as &$a){
      $a->total = $a->ghost + $a->living;
      $a = $pm->parsePlayer($a);
    }
    return $this->view->render($response, 'election/candidates.tpl',[
      'interval' => $interval,
      'admins' => $candidates,
      'list' => str_replace(['(',')',"'"], '', $list)
    ]);
  }
  public function mapularity ($request, $response, $args) {
    $mapularity = $this->DB->run("SELECT 
    date_format(r.initialize_datetime,'%M %Y') as `date`,
        count(r.id) as rounds, 
        LOWER(IF(ISNULL(r.map_name), 'Undefined', replace(replace(r.map_name, '_', ' '),' ', ''))) as `map_name`
        FROM tbl_round r
        WHERE r.initialize_datetime > '2017-02-29'
        GROUP BY map_name, MONTH(r.initialize_datetime), YEAR(r.initialize_datetime)
        HAVING rounds > 1
        ORDER BY r.initialize_datetime DESC");
    $tmp = [];
    $maps = [];
    foreach($mapularity as $row){
      $tmp[$row->date][$row->map_name] = $row->rounds;
      $maps[] = $row->map_name;
    }
    $maps = array_unique($maps);
    return $this->view->render($response, 'info/mapularity.tpl',[
      'maps' => $maps,
      'mapularity' => $tmp
    ]);
  }
}
