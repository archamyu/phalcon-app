<?php

use Phalcon\Mvc\Controller;
use PhpAmqpLib\Message\AMQPMessage;

class SignupController extends Controller
{
    public function indexAction() {
        $this->view->users = Users::find([
            'order' => 'id DESC'
        ]);
    }

    public function registerAction()
    {
        $post = $this->request->getPost();

        $user        = new Users();
        $user->name  = trim($post['name'] ?? '');
        $user->email = trim($post['email'] ?? '');

        $success = $user->save();

        $this->view->success = $success;

        if ($success) {
            $this->redis->set("user:{$user->id}", json_encode($user));

            $msg = new AMQPMessage(json_encode([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ]));
            $this->rabbitmq->basic_publish($msg, '', 'user_created');

            $message = "Thanks for registering!";
        } else {
            $message = "Sorry, the following problems were generated:<br>"
                . implode('<br>', $user->getMessages());
        }

        $this->view->message = $message;
    }

    public function editAction($id = null)
    {
        if ($this->request->isGet()) {
            $user = Users::findFirstById($id);
            $cached = $this->redis->get("user:$id");

            if ($cached) {
                return $this->view->user = json_decode($cached);
            }

            if (!$user) {
                return $this->response->redirect('signup');
            }

            $this->redis->set("user:{$user->id}", json_encode($user));
            $this->view->user = $user;
            return;
        }

        if ($this->request->isPost()) {
            $id   = $this->request->getPost('id', 'int');
            $name = trim($this->request->getPost('name', 'string'));
            $email = trim($this->request->getPost('email', 'email'));

            $user = Users::findFirstById($id);
            if (!$user) {
                return $this->response->redirect('signup');
            }

            if ($name === '' || $email === '') {
                return $this->response->redirect('signup/edit/' . $id);
            }

            $user->name = $name;
            $user->email = $email;

            if ($user->save()) {
                return $this->response->redirect('signup');
            }

            return $this->response->redirect('signup/edit/' . $id);
        }
    }

    public function deleteAction($id = null)
    {
        Users::findFirstById($id)->delete();

        $cached = $this->redis->get("user:$id");

        if ($cached) {
            $this->redis->del("user:$id");
        }

        return $this->response->redirect('signup');
    }
}