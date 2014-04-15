<?php
namespace Topxia\WebBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Topxia\Common\Paginator;
use Topxia\Common\ArrayToolkit;

class ArticleController extends BaseController
{

	public function indexAction(Request $request)
	{	

		$articleSetting = $this->getSettingService()->get('articleSetting', array());
		if (empty($articleSetting)) {
			$articleSetting = array('name' => '资讯频道', 'pageNums' => 20);
		}
		
		$categoryTree = $this->getCategoryService()->getCategoryTree();

		$conditions = array(
			'type' => 'article',
			'status' => 'published'
		);

        $paginator = new Paginator(
            $this->get('request'),
            $this->getArticleService()->searchArticleCount($conditions),
            $articleSetting['pageNums']
        );

		$latestArticles = $this->getArticleService()->searchArticles(
			$conditions, array('createdTime', 'DESC'), 
			$paginator->getOffsetCount(),
            $paginator->getPerPageCount()
		);

		$hottestArticles = $this->getArticleService()->searchArticles($conditions, array('hits' , 'DESC'), 0 , 10);
		
		foreach ($latestArticles as &$article) {
			$article['category'] = $this->getCategoryService()->getCategory($article['categoryId']);

		}

		$featuredConditions = array(
			'type' => 'article',
			'status' => 'published',
			'featured' => 1,
			'hasPicture' => 1
		);
		
		$featuredArticles = $this->getArticleService()->searchArticles(
			$featuredConditions,array('createdTime', 'DESC'),
			0,10
		);
		
		// @todo remove
		foreach ($featuredArticles as &$featuredArticle) {
			preg_match('/<img.+src=\"?(.+\.(jpg|gif|bmp|bnp|png))\"?.+>/i', $featuredArticle['body'], $matches);
			if (isset($matches[1])) {
				$featuredArticle['img'] = $matches[1];
			};
		};
		
		return $this->render('TopxiaWebBundle:Article:index.html.twig', array(
			'categoryTree' => $categoryTree,
			'latestArticles' => $latestArticles,
			'hottestArticles' => $hottestArticles,
			'featuredArticles' => $featuredArticles,
			'paginator' => $paginator,
			'articleSetting' => $articleSetting
		));
	}

	public function categoryAction(Request $request, $categoryCode)
	{	
		$category = $this->getCategoryService()->getCategoryByCode($categoryCode);

		if (empty($category)) {
			throw $this->createNotFoundException('资讯不存在');
		}

		// $conditions = array(
		// 	'categoryId' => $category['id'],
		// 	'includeChindren' => true,
		// );

		$categoryTree = $this->getCategoryService()->getCategoryTree();


		$topCategory = $this->getTopCategory($categoryTree,$category);

		$categoryIds = $this->getCategoryService()->findCategoryChildrenIds($category['id']);

		$categoryIds[] = $category['id']; 

		$articleSetting = $this->getSettingService()->get('article', array());
		if (empty($articleSetting)) {
			$articleSetting = array('name' => '资讯频道', 'pageNums' => 20);
		}

        $paginator = new Paginator(
            $this->get('request'),
            $this->getArticleService()->findArticlesCount($categoryIds),
            $articleSetting['pageNums']
        );

		$articles = $this->getArticleService()->findArticlesByCategoryIds(
			$categoryIds, 
			$paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

		foreach ($articles as &$article) {
			$article['category'] = $this->getCategoryService()->getCategory($article['categoryId']);
		}

		return $this->render('TopxiaWebBundle:Article:article-list.html.twig', array(
			'categoryTree' => $categoryTree,
			'categoryCode' => $categoryCode,
			'category' => $category,
			'articleSetting' => $articleSetting,
			'topCategory' => $topCategory,
			'articles' => $articles,
			'paginator' => $paginator
		));
	}

	public function detailAction(Request $request,$id)
	{
		$article = $this->getArticleService()->getArticle($id);
		
		if (empty($article)) {
			throw $this->createNotFoundException('文章不存在');
		}

		if ($article['status'] != 'published') {
			return $this->createMessageResponse('xxxx');
		}




		$articleSetting = $this->getSettingService()->get('articleSetting', array());
		$categoryTree = $this->getCategoryService()->getCategoryTree();

		$conditions = array(
			'status' => 'published'
		);

		$hottestArticles = $this->getArticleService()->searchArticles($conditions, array('hits' , 'DESC'), 0 , 10);
		$this->getArticleService()->hitArticle($id);

		$conditions['id'] = $id;
		$article = $this->getArticleService()->searchArticles($conditions, array('hits' , 'DESC'), 0 , 1);
		unset($conditions['id']);
		$conditions['idLessThan'] = $id;
		$article_next = $this->getArticleService()->searchArticles($conditions, array('hits' , 'DESC'), 0 , 1);
		unset($conditions['idLessThan']);
		$conditions['idMoreThan'] = $id;
		$article_previous = $this->getArticleService()->searchArticles($conditions, array('hits' , 'DESC'), 0 , 1);
		$article_next = $this->arrayChange($article_next);
		$article_previous = $this->arrayChange($article_previous);
		$article = $this->arrayChange($article);

		if(!empty($article)){
			$category = $this->getCategoryService()->getCategory($article['categoryId']);
			$tagIdsArray = explode(",", $article['tagIds']);
			$tags = $this->getTagService()->findTagsByIds($tagIdsArray);
		}else{
			return $this->createMessageResponse('error', '没有这篇文章！');
		}

		return $this->render('TopxiaWebBundle:Article:detail.html.twig', array(
			'categoryTree' => $categoryTree,
			'hottestArticles' => $hottestArticles,
			'articleSetting' => $articleSetting,
			'article_previous' => $article_previous,
			'article' => $article,
			'article_next' => $article_next,
			'tags' => $tags,
			'categoryName' => $category['name'],
			'categoryCode' => $category['code'],
		));
	}

	private function getTopCategory($categoryTree, $category)
	{
		$start = false;
		foreach (array_reverse($categoryTree) as $treeCategory) {
			if ($treeCategory['id'] == $category['id']) {
				$start = true;
			}

			if ($start && $treeCategory['depth'] ==1) {
				return $treeCategory;
			}
		}

		return null;
	}

	protected function arrayChange($changeArray){
		if(empty($changeArray)){
			return array();
		}

	    $newArray = array();

	    foreach($changeArray as $valueArr){
	    	foreach ($valueArr as $key => $value) {
	    		$newArray[$key] = $value;
	    	}
	    } 
	    return $newArray; 
	}

    private function getCategoryService()
    {
        return $this->getServiceKernel()->createService('Article.CategoryService');
    }

    private function getArticleService()
    {
        return $this->getServiceKernel()->createService('Article.ArticleService');
    }

    private function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

     private function getTagService()
     {
	    return $this->getServiceKernel()->createService('Taxonomy.TagService');
     }

}