<?php

namespace App\Http\Controllers;

use App\Jobs\SendExpoNotificationJob;
use App\Services\ExpoNotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
  public function __construct(
    protected ExpoNotificationService $expoService
  ) {}

  /**
   * When active new package
   */
  public function activeNewPackage($userIds)
  {
    SendExpoNotificationJob::dispatch(
      userIds: $userIds,
      title: "Explorez-le dès maintenant ! 📣✨",
      body: "Un nouveau module vient d'être ajouté à votre formation. 🎉🎉🎉",
      data: ['action' => 'training']
    );
  }

  /**
   * When receive new contract
   */
  public function receiveNewContract($userIds)
  {
    SendExpoNotificationJob::dispatch(
      userIds: $userIds,
      title: "Nouveau devis ! ✍️",
      body: "📄 Un document vous a été envoyé.",
      data: ['action' => 'document']
    );
  }

  /**
   * When receive new contract
   */
  public function receiveNewInvoice($userIds)
  {
    SendExpoNotificationJob::dispatch(
      userIds: $userIds,
      title: "nouvelle facture !",
      body: "📄 Une facture vous a été envoyé.",
      data: ['action' => 'document']
    );
  }

  /**
   * When receive the invite
   */
  public function receiveInviteToJoinCompany($userIds)
  {
    SendExpoNotificationJob::dispatch(
      userIds: $userIds,
      title: "Nouvelle invitation ! 👋",
      body: "Vous avez reçu une invitation à rejoindre l'équipe.",
      data: ['action' => 'notification']
    );
  }

  /**
   * When receive reponse when member accept
   */
  public function receiveReponseAcceptJoinCompany($userIds, $name)
  {
    SendExpoNotificationJob::dispatch(
      userIds: $userIds,
      title: "Bravo ! 🎉",
      body: $name . " a accepté votre invitation. 🤝",
      data: ['action' => 'company']
    );
  }
  /**
   * When receive reponse when member accept
   */
  public function receiveReponseRefuseJoinCompany($userIds, $name)
  {
    SendExpoNotificationJob::dispatch(
      userIds: $userIds,
      title: "Quel dommage ! 💔",
      body: $name . " a décliné votre invitation.",
      data: ['action' => 'company']
    );
  }

  /**
   * Daily question
   */
  public function hasNewDailyQuestion($userIds, $title, $body)
  {
    SendExpoNotificationJob::dispatch(
      userIds: $userIds,
      title: $title,
      body: $body,
      data: ['action' => 'daily-question'],
    );
  }

  /**
   * New podcast
   */
  public function hasNewPodcast($userIds, $title, $body)
  {
    SendExpoNotificationJob::dispatch(
      userIds: $userIds,
      title: $title,
      body: $body,
      data: ['action' => 'podcast']
    );
  }

  /**
   * New video
   */
  public function hasNewVideo($userIds, $title, $body, $videoId = null)
  {
    SendExpoNotificationJob::dispatch(
      userIds: $userIds,
      title: $title,
      body: $body,
      data: [
        'action' => 'media',
        'id' => $videoId
      ]
    );
  }




  /**
   * Test send notification
   */
  public function sendTest(Request $request)
  {
    $id = $request->id;
    $type = $request->type;

    if ($type == 'daily-question') {
      $this->hasNewDailyQuestion(
        [$id],
        "Question du jour ! ❓📝",
        "Si vous ressentez des douleurs à l'arrière de la nuque, quelles en sont les principales causes ?"
      );
    }

    if ($type == 'podcast') {
      $this->hasNewPodcast(
        [$id],
        "Nouveau podcast! 🎧",
        "Podcast de OrthoMaster"
      );
    }

    // $this->activeNewPackage([$id]);
    // $this->receiveNewContract([$id]);
    // $this->receiveInviteToJoinCompany([$id]);
    // $this->receiveReponseAcceptJoinCompany([$id], 'OrthoMasterTester');
    // $this->receiveReponseRefuseJoinCompany([$id], 'OrthoMasterTester');

    // $this->hasNewVideo(
    //   [$id],
    //   "Nouveau Vidéo! ▶️",
    //   "Lion King"
    // );

    return response()->json(true);
  }

  /**
   * Check status of notification
   */
  public function checkReceipt(Request $request)
  {
    $result = $this->expoService->getReceipts([$request->id]);
    return response()->json($result);
  }
}
