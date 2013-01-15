<?php

namespace OpenSkedge\AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use OpenSkedge\AppBundle\Entity\User;
use OpenSkedge\AppBundle\Entity\Clock;
use OpenSkedge\AppBundle\Form\UserType;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\Form;

/**
 * User controller.
 *
 */
class UserController extends Controller
{
    /**
     * Lists all User entities.
     *
     */
    public function indexAction()
    {
        if (false === $this->get('security.context')->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException();
        }

        $em = $this->getDoctrine()->getManager();

        $entities = $em->getRepository('OpenSkedgeBundle:User')->findAll();

        return $this->render('OpenSkedgeBundle:User:index.html.twig', array(
            'userstitle' => 'Users',
            'entities' => $entities,
        ));
    }

    /**
     * Finds and displays a User entity.
     *
     */
    public function viewAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        if (is_null($id))
            $id = $this->getUser()->getId();
        $entity = $em->getRepository('OpenSkedgeBundle:User')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find User entity.');
        }

        $deleteForm = $this->createDeleteForm($id);
        return $this->render('OpenSkedgeBundle:User:view.html.twig', array(
            'entity'      => $entity,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Creates a new User entity.
     *
     */
    public function newAction(Request $request)
    {
        if (false === $this->get('security.context')->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException();
        }

        $entity  = new User();
        $form = $this->createForm(new UserType(), $entity);

        if ($request->getMethod() == 'POST') {
            $form->bind($request);
            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $encoder = $this->get('security.encoder_factory')->getEncoder($entity);
                $password = $encoder->encodePassword($entity->getPassword(), $entity->getSalt());
                $entity->setPassword($password);
                $clock = new Clock();
                $em->persist($clock);
                $em->flush();
                $entity->setClock($clock);
                $em->persist($entity);
                $em->flush();

                return $this->redirect($this->generateUrl('user_view', array('id' => $entity->getId())));
            }
        }

        return $this->render('OpenSkedgeBundle:User:new.html.twig', array(
            'entity' => $entity,
            'form'   => $form->createView(),
        ));
    }

    /**
     * Edits an existing User entity.
     *
     */
    public function editAction(Request $request, $id)
    {
        if (false === $this->get('security.context')->isGranted('ROLE_ADMIN') && $id != $this->getUser()->getId()) {
            throw new AccessDeniedException();
        }

        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('OpenSkedgeBundle:User')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find User entity.');
        }

        $originalPassword = $entity->getPassword();

        $deleteForm = $this->createDeleteForm($id);
        $editForm = $this->createForm(new UserType(), $entity);
        if($id == $this->getUser()->getId()) {
            $editForm->remove('min');
            $editForm->remove('max');
            $editForm->remove('color');
            $editForm->remove('supnotes');
            $editForm->remove('isActive');
            $editForm->remove('group');
            $editForm->remove('supervisors');
        }

        if ($request->getMethod() == 'POST') {
            $editForm->bind($request);
            if ($editForm->isValid()) {
                $this->cleanupCollections($editForm);
                $plainPassword = $editForm->getViewData()->getPassword();
                if (!empty($plainPassword))  {
                    $encoder = $this->container->get('security.encoder_factory')->getEncoder($entity);
                    $password = $encoder->encodePassword($plainPassword, $entity->getSalt());
                    $entity->setPassword($password);
                } else {
                    $entity->setPassword($originalPassword);
                }
                $em->persist($entity);
                $em->flush();

                return $this->redirect($this->generateUrl('user_view', array('id' => $id)));
            }
        }

        return $this->render('OpenSkedgeBundle:User:edit.html.twig', array(
            'entity'      => $entity,
            'edit_form'   => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a User entity.
     *
     */
    public function deleteAction(Request $request, $id)
    {
        if (false === $this->get('security.context')->isGranted('ROLE_ADMIN') || $id == $this->getUser->getId()) {
            throw new AccessDeniedException();
        }

        $form = $this->createDeleteForm($id);
        $form->bind($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $entity = $em->getRepository('OpenSkedgeBundle:User')->find($id);

            if (!$entity) {
                throw $this->createNotFoundException('Unable to find User entity.');
            }

            $em->remove($entity);
            $em->flush();
        }

        return $this->redirect($this->generateUrl('user'));
    }

    private function createDeleteForm($id)
    {
        return $this->createFormBuilder(array('id' => $id))
            ->add('id', 'hidden')
            ->getForm()
        ;
    }

    /**
     * Lists all supervisors for the User.
     */
    public function supervisorsAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        if(is_null($id)) {
            $user = $this->getUser();
            $userstitle = 'My Supervisors';
        } else {
            $user = $em->getRepository('OpenSkedgeBundle:User')->find($id);

            if (!$user) {
                throw $this->createNotFoundException('Unable to find User.');
            }

            $userstitle = $user->getName()."'s Supervisors";
        }

        $entities = $user->getSupervisors();

        return $this->render('OpenSkedgeBundle:User:index.html.twig', array(
            'displayonly' => true,
            'userstitle' => $userstitle,
            'entities' => $entities,
        ));
    }

    /**
     * Lists all employees for the User.
     */
    public function employeesAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        if(is_null($id)) {
            $user = $this->getUser();
            $userstitle = 'My Employees';
        } else {
            $user = $em->getRepository('OpenSkedgeBundle:User')->find($id);

            if (!$user) {
                throw $this->createNotFoundException('Unable to find User.');
            }

            $userstitle = $user->getName()."'s Employees";
        }

        $entities = $user->getEmployees();

        return $this->render('OpenSkedgeBundle:User:index.html.twig', array(
            'displayonly' => true,
            'userstitle' => $userstitle,
            'entities' => $entities,
        ));
    }

    public function colleaguesAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        if(is_null($id)) {
            $user = $this->getUser();
            $userstitle = 'My Colleagues';
        } else {
            $user = $em->getRepository('OpenSkedgeBundle:User')->find($id);

            if (!$user) {
                throw $this->createNotFoundException('Unable to find User.');
            }

            $userstitle = $user->getName()."'s Colleagues";
        }

        $qb = $em->createQueryBuilder();

        $schedulePeriods = $qb->select('sp')
                              ->from('OpenSkedgeBundle:SchedulePeriod', 'sp')
                              ->where('sp.startTime < CURRENT_TIMESTAMP()')
                              ->andWhere('sp.endTime > CURRENT_TIMESTAMP()')
                              ->getQuery()
                              ->getResult();
        $schedules = array();

        $userPositions = array();

        foreach($schedulePeriods as $schedulePeriod) {
            try {
                $userPositions[] = $em->createQueryBuilder()
                                    ->select('p', 's')
                                    ->from('OpenSkedgeBundle:Schedule', 's')
                                    ->innerJoin('s.position', 'p', 'WITH', '(s.user = :uid AND s.schedulePeriod = :spid)')
                                    ->setParameters(array('uid' => $user->getId(), 'spid' => $schedulePeriod->getId()))
                                    ->getQuery()
                                    ->setMaxResults(1)
                                    ->getSingleResult()
                                    ->getPosition()
                                    ->getId();
            } catch (\Doctrine\ORM\NoResultException $e) {
                // It's aight.
            }
        }

        foreach($schedulePeriods as $schedulePeriod) {
            foreach($userPositions as $upid) {
                $schedules[] = $em->getRepository('OpenSkedgeBundle:Schedule')->findBy(array(
                    'schedulePeriod' => $schedulePeriod,
                    'position'       => $upid
                ));
            }
        }

        $entities = array();

        for ($i = 0; $i < count($schedules); $i++) {
            foreach($schedules[$i] as $schedule)
            {
                if ($schedule->getUser()->getId() != $user->getId())
                    $entities[] = $schedule->getUser();
            }
        }

        $entities = array_unique($entities);

        return $this->render('OpenSkedgeBundle:User:index.html.twig', array(
            'displayonly' => true,
            'userstitle' => $userstitle,
            'entities' => $entities,
        ));
    }

    /**
     * Ensure that any removed items collections actually get removed
     *
     * @param \Symfony\Component\Form\Form $form
     */
    protected function cleanupCollections(Form $form)
    {
        $children = $form->getChildren();

        foreach ($children as $childForm) {
            $data = $childForm->getData();
            if ($data instanceof Collection) {

                // Get the child form objects and compare the data of each child against the object's current collection
                $proxies = $childForm->getChildren();
                foreach ($proxies as $proxy) {
                    $entity = $proxy->getData();
                    if (!$data->contains($entity)) {
                        // Entity has been removed from the collection
                        $em = $this->getDoctrine()->getEntityManager();
                        $em->remove($entity);
                    }
                }
            }
        }
    }
}
