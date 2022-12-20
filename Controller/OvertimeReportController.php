<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Controller;

use Doctrine\ORM\Exception\ORMException;
use App\Controller\AbstractController;
use App\Entity\Customer;
use App\Entity\Team;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Form\Model\DateRange;
use App\Model\DailyStatistic;
use App\Reporting\WeekByUser;
use App\Repository\Query\BaseQuery;
use App\Repository\Query\TimesheetQuery;
use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;
use DateTime;
use Exception;
use KimaiPlugin\ApprovalBundle\Entity\Approval;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalWorkdayHistory;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;
use KimaiPlugin\ApprovalBundle\Enumeration\FormEnum;
use KimaiPlugin\ApprovalBundle\Form\SettingsForm;
use KimaiPlugin\ApprovalBundle\Form\WeekByUserForm;
use KimaiPlugin\ApprovalBundle\Form\AddWorkdayHistoryForm;
use KimaiPlugin\ApprovalBundle\Form\OvertimeByUserForm;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalHistoryRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalWorkdayHistoryRepository;
use KimaiPlugin\ApprovalBundle\Repository\ReportRepository;
use KimaiPlugin\ApprovalBundle\Settings\ApprovalSettingsInterface;
use KimaiPlugin\ApprovalBundle\Toolbox\BreakTimeCheckToolGER;
use KimaiPlugin\ApprovalBundle\Toolbox\Formatting;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/approval-report")
 */
class OvertimeReportController extends AbstractController
{
    private $settingsTool;
    private $approvalRepository;
    private $approvalHistoryRepository;
    private $approvalWorkdayHistoryRepository;
    private $userRepository;
    private $formatting;
    private $timesheetRepository;
    private $breakTimeCheckToolGER;
    private $reportRepository;
    private $approvalSettings;

    public function __construct(
        SettingsTool $settingsTool,
        UserRepository $userRepository,
        ApprovalHistoryRepository $approvalHistoryRepository,
        ApprovalRepository $approvalRepository,
        ApprovalWorkdayHistoryRepository $approvalWorkdayHistoryRepository,
        Formatting $formatting,
        TimesheetRepository $timesheetRepository,
        BreakTimeCheckToolGER $breakTimeCheckToolGER,
        ReportRepository $reportRepository,
        ApprovalSettingsInterface $approvalSettings
    ) {
        $this->settingsTool = $settingsTool;
        $this->userRepository = $userRepository;
        $this->approvalHistoryRepository = $approvalHistoryRepository;
        $this->approvalRepository = $approvalRepository;
        $this->approvalWorkdayHistoryRepository = $approvalWorkdayHistoryRepository;
        $this->formatting = $formatting;
        $this->timesheetRepository = $timesheetRepository;
        $this->breakTimeCheckToolGER = $breakTimeCheckToolGER;
        $this->reportRepository = $reportRepository;
        $this->approvalSettings = $approvalSettings;
    }

    private function canManageTeam(): bool
    {
        return $this->isGranted('view_team_approval');
    }

    private function canManageAllPerson(): bool
    {
        return $this->isGranted('view_all_approval');
    }

    /** 
     * @Route(path="/overtime_by_user", name="overtime_bundle_report", methods={"GET","POST"})
     * @throws Exception
     */
    public function overtimeByUser(Request $request): Response
    {
        $users = $this->getUsers();
        $firstUser = empty($users) ? $this->getUser() : $users[0];

        $values = new WeekByUser();
        $values->setUser($firstUser);

        $form = $this->createForm(OvertimeByUserForm::class, $values, [
            'users' => $users,
        ]);

        $form->submit($request->query->all(), false);

        if ($values->getUser() === null) {
            $values->setUser($firstUser);
        }

        $selectedUser = $values->getUser();
        $userId = $request->query->get('user');

        $weeklyEntries = $this->approvalRepository->findBy(['user' => $selectedUser]);
        file_put_contents("C:/temp/blub.txt", "blub " . json_encode($weeklyEntries) . "\n", FILE_APPEND);

        return $this->render('@Approval/overtime_by_user.html.twig', [
            'current_tab' => 'overtime_by_user',
            'form' => $form->createView(),
            'user' => $selectedUser,    
            'userId' => $userId,
            'showToApproveTab' => $this->canManageAllPerson() || $this->canManageTeam(),
            'showSettings' => $this->isGranted('ROLE_SUPER_ADMIN'),
            'showSettingsWorkdays' => $this->isGranted('ROLE_SUPER_ADMIN') && $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY),
            'showOvertime' => $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY)
        ]);
    }

    private function getUsers(): array
    {
        if ($this->canManageAllPerson()) {
            $users = $this->userRepository->findAll();
        } elseif ($this->canManageTeam()) {
            $users = [];
            $user = $this->getUser();
            /** @var Team $team */
            foreach ($user->getTeams() as $team) {
                if (\in_array($user, $team->getTeamleads())) {
                    array_push($users, ...$team->getUsers());
                } else {
                    $users[] = $user;
                }
            }
            $users = array_unique($users);
        } else {
            $users = [$this->getUser()];
        }

        $users = array_reduce($users, function ($current, $user) {
            if ($user->isEnabled() && !$user->isSuperAdmin()) {
                $current[] = $user;
            }

            return $current;
        }, []);
        if (!empty($users)) {
            usort(
                $users,
                function (User $userA, User $userB) {
                    return strcmp(strtoupper($userA->getUsername()), strtoupper($userB->getUsername()));
                }
            );
        }

        return $users;
    }
}