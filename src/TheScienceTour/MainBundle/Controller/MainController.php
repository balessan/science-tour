<?php

namespace TheScienceTour\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Response;

class MainController extends Controller {

  public function homeAction() {
    // Use session.
    $session   = $this->get('session');
    $isErasmus = $session->get('isErasmus', FALSE);

    // Ensure indexes.
    // TODO: Find a better place to run this
    $dm = $this->get('doctrine_mongodb')->getManager();
    $dm->getSchemaManager()->ensureIndexes();
    /* @var $projectRepo \TheScienceTour\ProjectBundle\Repository\ProjectRepository */
    $projectRepo = $dm->getRepository('TheScienceTourProjectBundle:Project');

    // Projects sticked on the front page
    $projectListQuery = $projectRepo->findFrontPage($isErasmus);
    $projectList      = $projectListQuery->execute();

    // Projects around me
    $maxDistance      = 50; // km
    $mapHelper        = $this->get('the_science_tour_map.map_helper');
    $aroundMeProjects = NULL;

    $trucksList = $dm->getRepository('TheScienceTourEventBundle:Event')
      ->findTrucks();

    try {
      $userGeocode           = $mapHelper->getGeocode($_SERVER['REMOTE_ADDR']);
      $aroundMeProjectsQuery = $projectRepo->findGeoNear($userGeocode->getLatitude(), $userGeocode->getLongitude(), $maxDistance, $isErasmus);
      $aroundMeProjects      = $aroundMeProjectsQuery->execute();
    } catch (Exception $e) {
      $session->getFlashBag()->add('notice', $e->getMessage());
    }

    $response = $this->render('TheScienceTourMainBundle::home.html.twig', array(
      'projectList'      => $projectList,
      'aroundMeProjects' => $aroundMeProjects,
      'trucksList'       => $trucksList,
      'isErasmus'        => $isErasmus
    ));
    $response->headers->set('Accept-Language', 'en-US, fr-FR');

    return $response;
  }

  public function searchAction($request) {
    // Disabled in dev mode.
    if ($this->get('kernel')->getEnvironment() === 'dev') {
      return new Response('La recherche est désactivée en environnement de dev');
    }

    $finder        = $this->container->get('fos_elastica.finder.tst.project');
    $query         = new \Elastica\Query\QueryString($request);
    $term          = new \Elastica\Filter\Term(array('status' => 1));
    $filteredQuery = new \Elastica\Query\Filtered($query, $term);
    $result        = $finder->find($filteredQuery);

    return $this->render('TheScienceTourMainBundle::search.html.twig', array(
      'request' => urldecode($request),
      'result'  => $result
    ));
  }
}
