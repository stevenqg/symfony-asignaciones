<?php
namespace UserBundle\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\FormError;
use UserBundle\Entity\User;
use UserBundle\Form\UserType;
class UserController extends Controller
{
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        
        // $users = $em->getRepository('EMMUserBundle:User')->findAll();
        
        /*
        
        $res = 'Lista de usuarios: <br />';
        
        foreach($users as $user)
        {
            $res .= 'Usuario: ' . $user->getUsername() . ' - Email: ' . $user->getEmail() . '<br />';
        }
        
        return new Response($res);
        */
        
        $dql = "SELECT u FROM UserBundle:User u ORDER BY u.id DESC";
        $users = $em->createQuery($dql);
        
        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $users, $request->query->getInt('page', 1),
            3
        );
        
        return $this->render('UserBundle:User:index.html.twig', array('pagination' => $pagination));
    }
    
    public function addAction()
    {
        $user = new User();
        $form = $this->createCreateForm($user);
        
        return $this->render('UserBundle:User:add.html.twig', array('form' => $form->createView()));
    }
    
    private function createCreateForm(User $entity)
    {
        $form = $this->createForm(UserType::class, $entity, array(
                'action' => $this->generateUrl('user_create'),
                'method' => 'POST'
            ));
        
        return $form;
    }
    
    public function createAction(Request $request)
    {   
        $user = new User();
        $form = $this->createCreateForm($user);
        $form->handleRequest($request);
        
        if($form->isValid())
        {
            $password = $form->get('password')->getData();
            
            $passwordConstraint = new Assert\NotBlank();
            $errorList = $this->get('validator')->validate($password, $passwordConstraint);
            
            if(count($errorList) == 0)
            {
                $encoder = $this->container->get('security.password_encoder');
                $encoded = $encoder->encodePassword($user, $password);
                
                $user->setPassword($encoded);
                
                $em = $this->getDoctrine()->getManager();
                $em->persist($user);
                $em->flush();
                
                $this->addFlash('mensaje', 'the user has been created');
                
                return $this->redirectToRoute('user_index');                
            }
            else
            {
                $errorMessage = new FormError($errorList[0]->getMessage());
                $form->get('password')->addError($errorMessage);
            }
        }
        
        return $this->render('UserBundle:User:add.html.twig', array('form' => $form->createView()));
    }
    
    public function editAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $user = $em->getRepository('UserBundle:User')->find($id);
        
        if(!$user)
        {
            throw $this->createNotFoundException("user not found");
        }
        
        $form = $this->createEditForm($user);
        
        return $this->render('UserBundle:User:edit.html.twig', array('user' => $user, 'form' => $form->createView()));
        
    }
    
    private function createEditForm(User $entity)
    {
        $form = $this->createForm(UserType::class, $entity, array('action' => $this->generateUrl('user_update', array('id' => $entity->getId())), 'method' => 'PUT'));
        
        return $form;
    }
    
    public function updateAction($id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        
        $user = $em->getRepository('UserBundle:User')->find($id);
        if(!$user)
        {
            throw $this->createNotFoundException('user not found');
        }
        
        $form = $this->createEditForm($user);
        $form->handleRequest($request);
        
        if($form->isSubmitted() && $form->isValid())
        {
            $password = $form->get('password')->getData();
            if(!empty($password))
            {
                $encoder = $this->container->get('security.password_encoder');
                $encoded = $encoder->encodePassword($user, $password);
                $user->setPassword($encoded);
            }
            else
            {
                $recoverPass = $this->recoverPass($id);
                $user->setPassword($recoverPass[0]['password']);                
            }
            
            if($form->get('role')->getData() == 'ROLE_ADMIN')
            {
                $user->setIsActive(1);
            }
            $em->flush();
            
            $this->addFlash('mensaje', 'the user has been modified.');
            return $this->redirectToRoute('user_edit', array('id' => $user->getId()));
        }
        return $this->render('UserBundle:User:edit.html.twig', array('user' => $user, 'form' => $form->createView()));
    }
    
    private function recoverPass($id)
    {
        $em = $this->getDoctrine()->getManager();
        $query = $em->createQuery(
            'SELECT u.password
            FROM UserBundle:User u
            WHERE u.id = :id'    
        )->setParameter('id', $id);
        
        $currentPass = $query->getResult();
        
        return $currentPass;
    }
    
    public function viewAction($id)
    {
        $repository = $this->getDoctrine()->getRepository('UserBundle:User');
        
        $user = $repository->find($id);
        
        if(!$user)
        {
            throw $this->createNotFoundException('user not found.');
        }
        
        $deleteForm = $this->createDeleteForm($user);
        
        return $this->render('UserBundle:User:view.html.twig', array('user' => $user, 'delete_form' => $deleteForm->createView()));
    }
    
    private function createDeleteForm($user)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('user_delete', array('id' => $user->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }
    
    public function deleteAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();
        
        $user = $em->getRepository('UserBundle:User')->find($id);
        
        if(!$user)
        {
            throw $this->createNotFoundException('user not found.');
        }
        
        $form = $this->createDeleteForm($user);
        $form->handleRequest($request);
        
        if($form->isSubmitted() && $form->isValid())
        {
            $em->remove($user);
            $em->flush();
            
            $this->addFlash('mensaje', 'the user has been deleted.');
            return $this->redirectToRoute('user_index');            
        }
    }
}