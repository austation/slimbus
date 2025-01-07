<?php

namespace Statbus\Controllers;

use Psr\Container\ContainerInterface;
use Statbus\Models\Death as Death;
use Statbus\Controllers\Controller as Controller;

class DeathController Extends Controller{

  public function __construct(ContainerInterface $container) {
    parent::__construct($container);

    $this->router = $this->container->get('router');
    $this->deathModel = new Death($this->container->get('settings')['statbus']);

    $this->pages = ceil($this->DB->cell("SELECT count(death.id) FROM death
        LEFT JOIN round ON round.id = death.round_id
        WHERE round.end_datetime IS NOT NULL") / $this->per_page);

    $this->breadcrumbs['Deaths'] = $this->router->pathFor('death.index');

    $this->url = $this->router->pathFor('death.index');
  }

  public function index($request, $response, $args) {
    if(isset($args['page'])) {
      $this->page = filter_var($args['page'], FILTER_VALIDATE_INT);
    }
    $deaths = $this->DB->run("SELECT 
        death.id,
        death.pod,
        death.x_coord AS x,
        death.y_coord AS y,
        death.z_coord AS z,
        death.server_port AS port,
        death.round_id AS round,
        death.mapname,
        death.tod,
        death.job,
        death.special,
        death.name,
        death.byondkey,
        death.laname,
        death.lakey,
        death.bruteloss AS brute,
        death.brainloss AS brain,
        death.fireloss AS fire,
        death.oxyloss AS oxy,
        death.toxloss AS tox,
        death.cloneloss AS clone,
        death.staminaloss AS stamina,
        death.last_words,
        death.suicide
        FROM death
        LEFT JOIN round ON round.id = death.round_id
        WHERE round.end_datetime IS NOT NULL
        ORDER BY death.tod DESC
        LIMIT ?,?", ($this->page * $this->per_page) - $this->per_page, $this->per_page);
    foreach ($deaths as &$death){
      $death = $this->deathModel->parseDeath($death);
    }
    return $this->view->render($response, 'death/listing.tpl',[
      'deaths'      => $deaths,
      'death'       => $this,
      'wide'        => true,
      'breadcrumbs' => $this->breadcrumbs
    ]);
  }

  public function DeathsForRound($request, $response, $args) {
    if(isset($args['round'])) {
      $round = filter_var($args['round'], FILTER_VALIDATE_INT);
    }
    if(isset($args['page'])) {
      $this->page = filter_var($args['page'], FILTER_VALIDATE_INT);
    } $format = null;
    if(isset($request->getQueryParams()['format'])) {
      $format = filter_var($request->getQueryParams()['format'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    }
    if(!$format){
    $this->pages = ceil($this->DB->cell("SELECT count(death.id) FROM death WHERE death.round_id = ?", $round) / $this->per_page);
    } else {
        $this->per_page = 1000;
    }
    $deaths = $this->DB->run("SELECT 
        death.id,
        death.pod,
        death.x_coord AS x,
        death.y_coord AS y,
        death.z_coord AS z,
        death.server_port AS port,
        death.round_id AS round,
        death.mapname,
        death.tod,
        death.job,
        death.special,
        death.name,
        death.byondkey,
        death.laname,
        death.lakey,
        death.bruteloss AS brute,
        death.brainloss AS brain,
        death.fireloss AS fire,
        death.oxyloss AS oxy,
        death.toxloss AS tox,
        death.cloneloss AS clone,
        death.staminaloss AS stamina,
        death.last_words,
        death.suicide
        FROM death
        LEFT JOIN round ON round.id = death.round_id
        WHERE round.end_datetime IS NOT NULL
        AND death.round_id = ?
        ORDER BY death.tod DESC
        LIMIT ?,?", 
          $round,
          ($this->page * $this->per_page) - $this->per_page,
          $this->per_page
        );
    foreach ($deaths as &$death){
      $death = $this->deathModel->parseDeath($death);
    }
    if('json' === $format){
        return $response->withJson($deaths);
    }
    $url = parent::getFullURL($this->router->pathFor('round.single',['id'=>$args['round']]));
    $this->ogdata['url'] = $url;
    $this->ogdata['title'] = "Deaths for Round #$round";
    $this->breadcrumbs['Round '.$args['round']] = $url;

    $this->url = $this->router->pathFor('death.round',['round'=>$args['round']]);

    return $this->view->render($response, 'death/listing.tpl',[
      'deaths'      => $deaths,
      'death'       => $this,
      'wide'        => true,
      'breadcrumbs' => $this->breadcrumbs,
      'ogdata'      => $this->ogdata
    ]);
  }

  public function single($request, $response, $args) {
    $id = filter_var($args['id'],FILTER_VALIDATE_INT);
    $death = $this->DB->row("SELECT 
        death.id,
        death.pod,
        death.x_coord AS x,
        death.y_coord AS y,
        death.z_coord AS z,
        death.server_port AS port,
        death.round_id AS round,
        death.mapname,
        death.tod,
        death.job,
        death.special,
        death.name,
        death.byondkey,
        death.laname,
        death.lakey,
        death.bruteloss AS brute,
        death.brainloss AS brain,
        death.fireloss AS fire,
        death.oxyloss AS oxy,
        death.toxloss AS tox,
        death.cloneloss AS clone,
        death.staminaloss AS stamina,
        death.last_words,
        death.suicide
        FROM death
        LEFT JOIN round ON round.id = death.round_id
        WHERE round.shutdown_datetime IS NOT NULL
        AND death.id = ?", $id);
    $death = $this->deathModel->parseDeath($death);
    $url = parent::getFullURL($this->router->pathFor('death.single',['id'=>$death->id]));
    $this->breadcrumbs[$death->id] = $url;
    if($death->lakey) {
      $this->ogdata['title'] = "RIP $death->name - $death->tod, murdered by $death->laname";
    } else {
      $this->ogdata['title'] = "RIP $death->name - $death->tod";
    }
    $this->ogdata['description']= "At $death->mapname's $death->pod during round $death->round. ";
    if($death->last_words) {
      $this->ogdata['description'].= "Their last words were '$death->last_words'. ";
    }
    $this->ogdata['description'].= "Cause of death: $death->cause";
    $this->ogdata['url'] = $url;
    return $this->view->render($response, 'death/death.tpl',[
      'death'       => $death,
      'breadcrumbs' => $this->breadcrumbs,
      'ogdata'      => $this->ogdata
    ]);
  }

  public function lastWords($request, $response, $args) {
    $deaths = $this->DB->run("SELECT 
        death.id,
        death.last_words
        FROM death
        LEFT JOIN round ON round.id = death.round_id
        WHERE death.last_words IS NOT NULL
        AND round.end_datetime IS NOT NULL
        GROUP BY death.last_words
        ORDER BY RAND()
        LIMIT 0, 1000");
    $this->breadcrumbs['Last Words'] = $this->router->pathFor('death.lastwords');
    return $this->view->render($response, 'death/lastwords.tpl',[
      'deaths'       => $deaths,
      'breadcrumbs' => $this->breadcrumbs
    ]);
  }

  public function deathMap($round){
    $deaths = $this->DB->run("SELECT 
        death.id,
        death.pod,
        death.x_coord AS x,
        death.y_coord AS y,
        death.tod,
        death.job,
        death.special,
        death.name,
        death.byondkey,
        death.laname,
        death.lakey,
        death.suicide
        FROM death
        LEFT JOIN round ON round.id = death.round_id
        WHERE round.end_datetime IS NOT NULL
        AND death.z_coord = 2
        AND death.round_id = ?
        ORDER BY death.tod DESC", $round);
    return json_encode($deaths);
  }
}
