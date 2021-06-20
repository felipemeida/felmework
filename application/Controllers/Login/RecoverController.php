<?php

namespace Agencia\Close\Controllers\Login;

use Agencia\Close\Adapters\EmailAdapter;
use Agencia\Close\Controllers\Controller;
use Agencia\Close\Helpers\Link\LinkRecover;
use Agencia\Close\Helpers\Result;
use Agencia\Close\Helpers\User\EmailUser;
use Agencia\Close\Helpers\User\Identification;
use Agencia\Close\Models\User\User;
use Agencia\Close\Services\Login\Logon;

class RecoverController extends Controller
{
    private string $email;
    private Result $result;

    public function __construct($router)
    {
        parent::__construct($router);
        $this->result = new Result();
    }

    public function recover(array $params)
    {
        $this->setParams($params);

        $linkRecover = new LinkRecover();
        $linkRecover->decrypt($this->params['recover-code']);

        $this->render('pages/user/recover.twig', [
            'valid_date' => $linkRecover->isValidData(),
            'recover_code' => $this->params['recover-code'],
        ]);
    }

    public function sendMailRecover($email)
    {
        $this->setParams($email);
        $this->verifyIfSendEmail();

        if (!$this->result->getError()) {
            $this->verifyIfEmailIsFounded();
        }
        if (!$this->result->getError()) {
            $this->sendEmail();
        }
        echo $this->result->getResultJson();
    }

    private function sendEmail()
    {
        $user = new User();
        $userFounded = $user->emailExist($this->email)->getResult()[0];

        $email = new EmailAdapter();
        $email->setSubject('Instruções para resetar senha - ' . NAME);
        $data = [
            'user_name' => $userFounded['nome'],
            'company_slug' => $this->params['slug-company'],
            'link' => LinkRecover::generate($this->email)
        ];
        $email->setBody('components/email/emailRecover.twig', $data);
        $email->addAddress($this->email);
        $email->send();
        $this->result = $email->getResult();
    }

    private function verifyIfSendEmail(): void
    {
        if (!isset($this->params['email'])) {
            $this->result->setError(true);
            $this->result->setMessage('E-mail não foi enviado!');
        }
    }

    private function verifyIfEmailIsFounded(): void
    {
        $this->email = $this->params['email'];
        $identification = new Identification();
        $identification->setType('email');
        $identification->setIdentification($this->email);

        if (!EmailUser::verifyIfEmailExist($identification)) {
            $this->result->setError(true);
            $this->result->setMessage('E-mail não encontrado!');
        }
    }

    public function changePassword(array $params)
    {
        $this->setParams($params);
        $linkRecover = new LinkRecover();
        $linkRecover->decrypt($this->params['recover-code']);

        if ($linkRecover->isValidData()) {
            $email = $linkRecover->getEmail();
            $user = new User();
            $result = $user->changePasswordByEmail($email, $this->params['password']);
            if ($result) {
                $logon = new Logon();
                $logon->loginByEmail($email, $this->params['password'], $this->params['slug-company']);

                $this->router->redirect("home", [
                    "slug-company" => $this->params['slug-company']
                ]);
            }
        }
    }
}