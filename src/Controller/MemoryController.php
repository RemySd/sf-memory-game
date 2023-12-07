<?php

namespace App\Controller;

use App\Service\Memory\Cell;
use App\Service\Memory\Grid;
use App\Entity\MemoryGameHistory;
use App\Form\MemoryGameHistoryType;
use App\Service\Memory\MemoryManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MemoryController extends AbstractController
{
    #[Route('/', name: 'app_memory')]
    public function index(): Response
    {
        return $this->render('memory/index.html.twig');
    }

    #[Route('/game-initialization ', name: 'app_memory_initialization')]
    public function initialization(MemoryManager $memoryManager): Response
    {
        $memoryGrid = $memoryManager->initializeMemoryParty(2, 2);
        $memoryManager->saveMemoryGrid($memoryGrid);

        return $this->redirectToRoute('app_memory_play');
    }

    #[Route('/play', name: 'app_memory_play')]
    public function play(
        MemoryManager $memoryManager,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $momeryGrid = $memoryManager->getMemoryGrid();
        $cellPosition = $request->query->get('cell');

        if ($cellPosition != null) {

            /** 
             * @var Cell[]
             */
            $cellsToCheck = $momeryGrid->getCellToCheck();

            if (count($cellsToCheck) === 2) {
                $cell1 = array_shift($cellsToCheck);
                $cell2 = array_shift($cellsToCheck);

                if ($cell1->getImage() === $cell2->getImage()) {
                    $momeryGrid->cellToPairing($cell1, $cell2);
                } else {
                    $cell1->reset();
                    $cell2->reset();
                }
            }

            $currentCellClicked = $momeryGrid->getCellByPosition($cellPosition);
            $currentCellClicked->setFlip(true);
            $currentCellClicked->setShouldBeCheck(true);

            $momeryGrid = $memoryManager->saveMemoryGrid($momeryGrid);
        }

        $parameters = ['memoryGrid' => $momeryGrid];

        if ($momeryGrid->isOver()) {
            $memoryGameHistory = new MemoryGameHistory();

            $form = $this->createForm(MemoryGameHistoryType::class, $memoryGameHistory);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $memoryGameHistory->setCreatedAt((new \DateTimeImmutable('now')));
                $memoryGameHistory->setScore(100);

                $entityManager->persist($memoryGameHistory);
                $entityManager->flush();

                return $this->redirectToRoute('app_memory_initialization');
            }

            $parameters['form'] = $form;
        }

        return $this->render('memory/play.html.twig', $parameters);
    }
}
