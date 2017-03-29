<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Framework\App\Test\Unit;

class ViewTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Magento\Framework\App\View
     */
    protected $_view;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $_layoutMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $_configScopeMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $_requestMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $_layoutProcessor;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $_actionFlagMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $_eventManagerMock;

    /**
     * @var \Magento\Framework\View\Result\Page|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $resultPage;

    /**
     * @var \Magento\Framework\App\Response\Http|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $response;

    protected function setUp()
    {
        $helper = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
        $this->_layoutMock = $this->getMock('Magento\Framework\View\Layout', [], [], '', false);
        $this->_requestMock = $this->getMock('Magento\Framework\App\Request\Http', [], [], '', false);
        $this->_configScopeMock = $this->getMock('Magento\Framework\Config\ScopeInterface');
        $this->_layoutProcessor = $this->getMock('Magento\Framework\View\Model\Layout\Merge', [], [], '', false);
        $this->_layoutMock->expects($this->any())->method('getUpdate')
            ->will($this->returnValue($this->_layoutProcessor));
        $this->_actionFlagMock = $this->getMock('Magento\Framework\App\ActionFlag', [], [], '', false);
        $this->_eventManagerMock = $this->getMock('Magento\Framework\Event\ManagerInterface');
        $pageConfigMock = $this->getMockBuilder('\Magento\Framework\View\Page\Config')->disableOriginalConstructor()
            ->getMock();
        $pageConfigMock->expects($this->any())
            ->method('publicBuild')
            ->willReturnSelf();

        $pageConfigRendererFactory = $this->getMockBuilder('Magento\Framework\View\Page\Config\RendererFactory')
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();

        $this->resultPage = $this->getMockBuilder('Magento\Framework\View\Result\Page')
            ->setConstructorArgs(
                $helper->getConstructArguments('Magento\Framework\View\Result\Page', [
                'request' => $this->_requestMock,
                'pageConfigRendererFactory' => $pageConfigRendererFactory,
                'layout' => $this->_layoutMock
                ])
            )
            ->setMethods(['renderResult', 'getConfig'])
            ->getMock();
        $this->resultPage->expects($this->any())
            ->method('getConfig')
            ->will($this->returnValue($pageConfigMock));
        $pageFactory = $this->getMockBuilder('Magento\Framework\View\Result\PageFactory')
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $pageFactory->expects($this->once())
            ->method('create')
            ->will($this->returnValue($this->resultPage));

        $this->response = $this->getMock('Magento\Framework\App\Response\Http', [], [], '', false);

        $this->_view = $helper->getObject(
            'Magento\Framework\App\View',
            [
                'layout' => $this->_layoutMock,
                'request' => $this->_requestMock,
                'response' => $this->response,
                'configScope' => $this->_configScopeMock,
                'eventManager' => $this->_eventManagerMock,
                'actionFlag' => $this->_actionFlagMock,
                'pageFactory' => $pageFactory
            ]
        );
    }

    public function testGetLayout()
    {
        $this->assertEquals($this->_layoutMock, $this->_view->getLayout());
    }

    /**
     * @expectedException \RuntimeException
     * @exceptedExceptionMessage 'Layout must be loaded only once.'
     */
    public function testLoadLayoutWhenLayoutAlreadyLoaded()
    {
        $this->_view->setIsLayoutLoaded(true);
        $this->_view->loadLayout();
    }

    public function testLoadLayoutWithDefaultSetup()
    {
        $this->_layoutProcessor->expects($this->at(0))->method('addHandle')->with('default');
        $this->_requestMock->expects(
            $this->any()
        )->method(
            'getFullActionName'
        )->will(
            $this->returnValue('action_name')
        );
        $this->_view->loadLayout();
    }

    public function testLoadLayoutWhenBlocksNotGenerated()
    {
        $this->_view->loadLayout('', false, true);
    }

    public function testLoadLayoutWhenXmlNotGenerated()
    {
        $this->_view->loadLayout('', true, false);
    }

    public function testGetDefaultLayoutHandle()
    {
        $this->_requestMock->expects($this->once())
            ->method('getFullActionName')
            ->will($this->returnValue('ExpectedValue'));

        $this->assertEquals('expectedvalue', $this->_view->getDefaultLayoutHandle());
    }

    public function testAddActionLayoutHandlesWhenPageLayoutHandlesExist()
    {
        $this->_requestMock->expects($this->once())
            ->method('getFullActionName')
            ->will($this->returnValue('Full_Action_Name'));

        $this->_layoutProcessor->expects($this->once())
            ->method('addHandle')
            ->with('full_action_name');

        $this->_view->addActionLayoutHandles();
    }

    public function testAddPageLayoutHandles()
    {
        $pageHandles = ['full_action_name', 'full_action_name_key_value'];
        $this->_requestMock->expects($this->once())
            ->method('getFullActionName')
            ->will($this->returnValue('Full_Action_Name'));

        $this->_layoutProcessor->expects($this->once())
            ->method('addHandle')
            ->with($pageHandles);
        $this->_view->addPageLayoutHandles(['key' => 'value']);
    }

    public function testGenerateLayoutBlocksWhenFlagIsNotSet()
    {
        $valueMap = [
            ['', \Magento\Framework\App\ActionInterface::FLAG_NO_DISPATCH_BLOCK_EVENT, false],
            ['', \Magento\Framework\App\ActionInterface::FLAG_NO_DISPATCH_BLOCK_EVENT, false],
        ];
        $this->_actionFlagMock->expects($this->any())->method('get')->will($this->returnValueMap($valueMap));
        $this->_view->generateLayoutBlocks();
    }

    public function testGenerateLayoutBlocksWhenFlagIsSet()
    {
        $valueMap = [
            ['', \Magento\Framework\App\ActionInterface::FLAG_NO_DISPATCH_BLOCK_EVENT, true],
            ['', \Magento\Framework\App\ActionInterface::FLAG_NO_DISPATCH_BLOCK_EVENT, true],
        ];
        $this->_actionFlagMock->expects($this->any())->method('get')->will($this->returnValueMap($valueMap));

        $this->_eventManagerMock->expects($this->never())->method('dispatch');
        $this->_view->generateLayoutBlocks();
    }

    public function testRenderLayoutIfActionFlagExist()
    {
        $this->_actionFlagMock->expects(
            $this->once()
        )->method(
            'get'
        )->with(
            '',
            'no-renderLayout'
        )->will(
            $this->returnValue(true)
        );
        $this->_eventManagerMock->expects($this->never())->method('dispatch');
        $this->_view->renderLayout();
    }

    public function testRenderLayoutWhenOutputNotEmpty()
    {
        $this->_actionFlagMock->expects($this->once())
            ->method('get')
            ->with('', 'no-renderLayout')
            ->will($this->returnValue(false));
        $this->_layoutMock->expects($this->once())->method('addOutputElement')->with('output');
        $this->resultPage->expects($this->once())->method('renderResult')->with($this->response);
        $this->_view->renderLayout('output');
    }

    public function testRenderLayoutWhenOutputEmpty()
    {
        $this->_actionFlagMock->expects($this->once())
            ->method('get')
            ->with('', 'no-renderLayout')
            ->will($this->returnValue(false));

        $this->_layoutMock->expects($this->never())->method('addOutputElement');
        $this->resultPage->expects($this->once())->method('renderResult')->with($this->response);
        $this->_view->renderLayout();
    }
}
