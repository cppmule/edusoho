<?php
namespace Topxia\WebBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\Paginator;

class QuizQuestionController extends BaseController
{
	public function indexAction(Request $request, $courseId)
	{
		$course   = $this->getCourseService()->tryManageCourse($courseId);
		$lessons  = $this->getCourseService()->getCourseLessons($courseId);
		
		$field    = $request->query->all();

		$question = array();
		if (empty($field['parentId'])){

			$conditions['parentId'] = $field['parentId'] = 0;

			if(empty($field['target'])){
				$conditions['target']['course'] = array($courseId);

				if (!empty($lessons)){
					$conditions['target']['lesson'] = ArrayToolkit::column($lessons,'id');;
				}

			}else{
				list($targetType, $targetId) = explode('-', $field['target']);

				$conditions['target'][$targetType] = array($targetId);
			}

		} else {

			$question = $this->getQuestionService()->getQuestion($field['parentId']);

			if (empty($question)){
				return $this->redirect($this->generateUrl('course_manage_question',array('courseId' => $courseId)));
			}

			$conditions['parentId'] = $field['parentId'];
			
		}

		if(!empty($field['type'])){
			$conditions['type'] = $field['type'];
		}

		if(!empty($field['keyword'])){
			$conditions['stem'] = $field['keyword'];
		}

		$questionCount = $this->getQuestionService()->searchQuestionCount($conditions);

		$paginator = new Paginator(
			$this->get('request'),
			$questionCount,
			10
		);

		$questions = $this->getQuestionService()->searchQuestion(
			$conditions,
			array('createdTime' ,'DESC'),
			$paginator->getOffsetCount(),
            $paginator->getPerPageCount()
		);

		$lessons = ArrayToolkit::index($lessons,'id');
		$users = $this->getUserService()->findUsersByIds(ArrayToolkit::column($questions, 'userId')); 
		$targets = $this->findQuestionTargets($courseId);
		
		return $this->render('TopxiaWebBundle:QuizQuestion:index.html.twig', array(
			'course' => $course,
			'questions' => $questions,
			'users' => $users,
			'lessons' => $lessons,
			'targets' => $targets,
			'question' => $question,
			'paginator' => $paginator,
			'field' => $field,
			'questionCount' => $questionCount
		));
	}

	public function createAction(Request $request, $courseId, $type)
	{
		$course = $this->getCourseService()->tryManageCourse($courseId);
		if (!in_array($type, array('choice','single_choice', 'fill', 'material', 'essay', 'determine'))) {
			throw $this->createNotFoundException('该项目问题类型不存在');
		}
		$parentId = $request->query->get('parentId');

		if (empty($parentId)){
			$parentId = 0;
		} else {
			$parentQuestion = $this->getQuestionService()->getQuestion($parentId);
			if (empty($parentQuestion)){
				return $this->redirect($this->generateUrl('course_manage_question',array('courseId' => $courseId)));
			}
		}

		$targets = $this->findQuestionTargets($courseId);

		$category = $this->getQuestionService()->findCategorysByCourseIds(array($courseId));

	    if ($request->getMethod() == 'POST') {
            $question = $request->request->all();
            $question['parentId'] = $parentId;

	        $question = $this->getQuestionService()->createQuestion($question);

			$submission = $request->request->get('submission');
	        if ($submission == 'continue'){
	        	
	        	$default = array(
	        		'courseId' => $courseId,
	        		'targetsDefault' => $question['targetType'].'-'.$question['targetId'],
	        		'questionDifficulty' => $question['difficulty'],
	        		'type' => $type,
	        		'parentId' => $parentId,
	        	);

	        	$this->setFlashMessage('success', '题目添加成功，请继续添加！');

	            return $this->redirect($this->generateUrl('course_manage_question_create',$default));

	        } else if ($submission == 'submit'){

	        	$this->setFlashMessage('success', '题目添加成功！');

		        if ($type == 'material'){
					$parentId = $question['id'];
				}

	        	return $this->redirect($request->query->get('goto', $this->generateUrl('course_manage_question',array('courseId' => $courseId,'parentId' => $parentId))));
	        }
        }

		$targets['default'] = $request->query->get('targetsDefault', '');

		$parentQuestion['difficulty'] = $request->query->get('questionDifficulty');

		$question['difficulty'] = $request->query->get('questionDifficulty');

		return $this->render('TopxiaWebBundle:QuizQuestion:modal.html.twig', array(
			'course' => $course,
			'type' => $type,
			'targets' => $targets,
			'parentId' => $parentId,
			'parentQuestion' => $parentQuestion,
			'category' => $category,
			'question' => $question
		));
	}


	public function editAction(Request $request, $courseId, $id)
	{
		$course = $this->getCourseService()->tryManageCourse($courseId);
		$question = $this->getQuestionService()->getQuestion($id);
		if (empty($question)){
			throw $this->createNotFoundException('该项目问题问题不存在');
		}
		$targets = $this->findQuestionTargets($courseId);

		$category = $this->getQuestionService()->findCategorysByCourseIds(array($courseId));

	    if ($request->getMethod() == 'POST') {

            $question = $request->request->all();

	        $question = $this->getQuestionService()->updateQuestion($id, $question);

	        $this->setFlashMessage('success', '题目修改成功！');

            return $this->redirect($request->query->get('goto', $this->generateUrl('course_manage_question',array('courseId' => $courseId,'parentId' => $question['parentId']))));
        }

        $question['choices'] = array();
        if (!empty($question['metas']['choices'])) {
            foreach ($question['metas']['choices'] as $key => $choice) {
                $choice = array('content' => $choice);
                if (in_array($key, $question['answer'])) {
                    $choice['isAnswer'] = true;
                }
                $question['choices'][] = $choice;
            }
        }

        $targets['default'] = $question['targetType'].'-'.$question['targetId'];
        $category['default'] = $question['categoryId'];
      
        return $this->render('TopxiaWebBundle:QuizQuestion:modal.html.twig', array(
			'question' => $question,
			'targets' => $targets,
            'parentId' => $question['parentId'],
			'course' => $course,
			'type' => $question['type'],
			'isEdit' => '1',
			'category' => $category,
		));
	}

	public function deleteAction(Request $request, $courseId, $id)
    {
		$course = $this->getCourseService()->tryManageCourse($courseId);
		$question = $this->getQuestionService()->getQuestion($id);
        if (empty($question)) {
            throw $this->createNotFoundException('question not found');
        }
        $this->getQuestionService()->deleteQuestion($id);
        return $this->createJsonResponse(true);
    }

    public function deletesAction(Request $request, $courseId)
    {   
		$course = $this->getCourseService()->tryManageCourse($courseId);
        $ids = $request->request->get('ids');
        if(empty($ids)){
        	throw $this->createNotFoundException();
        }
        foreach ($ids as $id) {
        	$this->getQuestionService()->deleteQuestion($id);
        }
        return $this->createJsonResponse(true);
    }

    public function uploadFileAction (Request $request, $courseId, $type)
    {
    	$course = $this->getCourseService()->getCourse($courseId);

    	if ($request->getMethod() == 'POST') {
	    	$originalFile = $this->get('request')->files->get('file');
	    	$file = $this->getUploadFileService()->addFile('quizquestion', 0, array('isPublic' => 1), 'local', $originalFile);
            return new Response(json_encode($file));
	    }
    }

    public function previewQuestionAction (Request $request, $id)
    {
    	$questions = $this->getQuestionService()->findQuestions(array($id));

        $question = $questions[$id];

        if (in_array($question['type'], array('single_choice', 'choice'))){
            foreach ($question['metas']['choices'] as $key => $choice) {
                $question['choices'][$key] = array( 'content' => $choice, 'questionId' => $key);
            }
        }

        if (empty($question)) {
    		throw $this->createNotFoundException('题目不存在！');
    	}

    	if ($question['type'] == 'material'){
    		$questions = $this->getQuestionService()->findQuestionsByParentIds(array($id));
            if (!empty($questions)) {
        		$questions = $this->getQuestionService()->findQuestions(ArrayToolkit::column($questions, 'id'));
            }

    		foreach ($questions as $key => $value) {
    			if (!in_array($value['type'], array('single_choice', 'choice'))){
    				continue;
    			}

    			foreach ($value['metas']['choices'] as $choiceKey => $content) {
    				$value['choices'][$choiceKey] = array('content' => $content, 'questionId' => $choiceKey);
    			}
    			ksort($value['choices']);
				$value['choices'] = array_values($value['choices']);
				foreach ($value['choices'] as $k => $v) {
					$v['choiceIndex'] = chr($k+65);
					$value['choices'][$k] = $v;
				}
				$questions[$key] = $value;
    		}

    		$question['questions'] = $questions;
    	} else {
    		if (in_array($question['type'], array('single_choice', 'choice'))){

				ksort($question['choices']);
				$question['choices'] = array_values($question['choices']);
				foreach ($question['choices'] as $k => $v) {
					$v['choiceIndex'] = chr($k+65);
					$question['choices'][$k] = $v;
				}
			}
    	}

    	$type = $question['type'] == 'single_choice'? 'choice' : $question['type'];
    	$questionPreview = true;

    	return $this->render('TopxiaWebBundle:QuizQuestionTest:question-preview-modal.html.twig', array(
            'question' => $question,
            'type' => $type,
            'questionPreview' => $questionPreview
        ));
    }


    private function findQuestionTargets($courseId)
    {
        $course = $this->getCourseService()->getCourse($courseId);
        if (empty($course))
            return null;
        $lessons = $this->getCourseService()->getCourseLessons($courseId);

        $targets = array();

        $targets['course'.'-'.$course['id']] = '本课程';

        foreach ($lessons as  $lesson) {
            if ($lesson['type'] == 'testpaper') {
                continue;
            }
            $targets['lesson'.'-'.$lesson['id']] = '课时'.$lesson['number'].'：'.$lesson['title'];
        }

        return $targets;
    }


	private function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

   	private function getQuestionService()
   	{
   		return $this -> getServiceKernel()->createService('Quiz.QuestionService');
   	}

   	private function getUploadFileService()
    {
        return $this->getServiceKernel()->createService('File.UploadFileService');
    }

}
