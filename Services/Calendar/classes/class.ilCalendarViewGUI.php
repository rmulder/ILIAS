<?php
/* Copyright (c) 1998-2017 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * @author Jesús López Reyes <lopez@leifos.com>
 * @version $Id$
 *
 * @ingroup ServicesCalendar
 */
class ilCalendarViewGUI
{
	const CAL_PRESENTATION_DAY = 1;
	const CAL_PRESENTATION_WEEK = 2;
	const CAL_PRESENTATION_MONTH = 3;
	const CAL_PRESENTATION_AGENDA_LIST = 9;

	/**
	 * @var \ILIAS\UI\Factory
	 */
	protected $ui_factory;

	/**
	 * @var \ILIAS\UI\Renderer
	 */
	protected $ui_renderer;

	/**
	 * @var ilCtrl
	 */
	protected $ctrl;

	/**
	 * @var integer
	 */
	protected $presentation_type;
	
	/**
	 * View initialization
	 * @param integer $a_calendar_presentation_type
	 */
	function initialize($a_calendar_presentation_type)
	{
		global $DIC;
		$this->ui_factory = $DIC->ui()->factory();
		$this->ui_renderer = $DIC->ui()->renderer();
		$this->ctrl = $DIC->ctrl();
		$this->lng = $DIC->language();
		$this->user = $DIC->user();
		$this->tabs_gui = $DIC->tabs();
		$this->tpl = $DIC["tpl"];
		$this->toolbar = $DIC->toolbar();
		$this->presentation_type = $a_calendar_presentation_type;
	}

	/**
	 * Get app for id
	 *
	 * @param
	 * @return
	 */
	function getCurrentApp()
	{
		// @todo: this needs optimization
		$events = $this->getEvents();
		foreach ($events as $item)
		{
			if ($item["event"]->getEntryId() == (int) $_GET["app_id"])
			{
				return $item;
			}
		}
		return null;
	}

	/**
	 * Get events
	 *
	 * @param
	 * @return
	 */
	function getEvents()
	{
//		$cat_info = ilCalendarCategories::_getInstance()->getCategoryInfo($cat_id);
//		initialize($a_mode,$a_source_ref_id = 0,$a_use_cache = false)

		$schedule = new ilCalendarSchedule(new ilDate(time(),IL_CAL_UNIX),ilCalendarSchedule::TYPE_PD_UPCOMING);

		switch ($this->presentation_type)
		{
			case self::CAL_PRESENTATION_AGENDA_LIST:
				$schedule->setPeriod(new ilDate($this->seed, IL_CAL_DATE),
					new ilDate($this->period_end_day, IL_CAL_DATE));
				break;
			case self::CAL_PRESENTATION_DAY:
				$schedule = new ilCalendarSchedule($this->seed, ilCalendarSchedule::TYPE_DAY);
				break;
			case self::CAL_PRESENTATION_WEEK:
				$schedule = new ilCalendarSchedule($this->seed, ilCalendarSchedule::TYPE_WEEK);
				break;
			case self::CAL_PRESENTATION_MONTH:
				$schedule = new ilCalendarSchedule($this->seed, ilCalendarSchedule::TYPE_MONTH);
				break;
		}

		//return $schedule->getChangedEvents(true);

		$schedule->addSubitemCalendars(true);
		$schedule->calculate();
		$ev = $schedule->getScheduledEvents();
		return $ev;
	}


	/**
	 * Get start/end date for item
	 *
	 * @param array $item item
	 * @return array
	 */
	function getDatesForItem($item)
	{
		$start = $item["dstart"];
		$end = $item["dend"];
		if($item["fullday"])
		{
			$start = new ilDate($start, IL_CAL_UNIX);
			$end = new ilDate($end, IL_CAL_UNIX);
		}
		else
		{
			$start = new ilDateTime($start, IL_CAL_UNIX);
			$end = new ilDateTime($end, IL_CAL_UNIX);
		}
		return array("start" => $start, "end" => $end);
	}

	/**
	 * Get modal for appointment (see similar code in ilCalendarBlockGUI)
	 */
	function getModalForApp()
	{
		global $DIC;

		$f = $this->ui_factory;
		$r = $this->ui_renderer;
		$ctrl = $this->ctrl;

		// @todo: this needs optimization
		$events = $this->getEvents();

		//item => array containing ilcalendary object, dstart of the event , dend etc.
		foreach ($events as $item)
		{
			$DIC->logger()->cal()->debug(" GET['dt'] => ".$_GET['dt']);
			$DIC->logger()->cal()->debug("calendar entry start => ".$item['event']->getTitle());
			$DIC->logger()->cal()->debug("item start => ".$item['dstart']);
			$DIC->logger()->cal()->debug("calendar entry start => ".$item['event']->getStart());


			if ($item["event"]->getEntryId() == (int) $_GET["app_id"] && $item['dstart'] == (int) $_GET['dt'])
			{
				$dates = $this->getDatesForItem($item);
				// content of modal
				include_once("./Services/Calendar/classes/class.ilCalendarAppointmentPresentationGUI.php");
				$next_gui = ilCalendarAppointmentPresentationGUI::_getInstance(new ilDate($this->seed, IL_CAL_DATE), $item);
				$content = $ctrl->getHTML($next_gui);
				$modal = $f->modal()->roundtrip(ilDatePresentation::formatPeriod($dates["start"], $dates["end"]),$f->legacy($content));
				echo $r->renderAsync($modal);
			}
		}
		exit();
	}

	//$a_title_forced used in plugins for rename the shy button title.
	function getAppointmentShyButton($a_calendar_entry, $a_dstart, $a_title_forced = "")
	{
		$f = $this->ui_factory;
		$r = $this->ui_renderer;

		$this->ctrl->setParameter($this, "app_id", $a_calendar_entry->getEntryId());
		$this->ctrl->setParameter($this,'dt',$a_dstart);
		$this->ctrl->setParameter($this,'seed',$this->seed->get(IL_CAL_DATE));
		$url = $this->ctrl->getLinkTarget($this, "getModalForApp", "", true, false);
		$this->ctrl->setParameter($this, "app_id", $_GET["app_id"]);
		$this->ctrl->setParameter($this, "dt", $_GET["dt"]);
		$this->ctrl->setParameter($this,'seed',$_GET["seed"]);

		$modal = $f->modal()->roundtrip('', [])->withAsyncRenderUrl($url);

		$title = ($a_title_forced == "")? $a_calendar_entry->getPresentationTitle() : $a_title_forced;

		$comps = [$f->button()->shy($title, "")->withOnClick($modal->getShowSignal()), $modal];

		return $r->render($comps);
	}

	//get active plugins.
	public function getActivePlugins()
	{
		global $ilPluginAdmin;

		$res = array();

		foreach($ilPluginAdmin->getActivePluginsForSlot(IL_COMP_SERVICE, "Calendar", "capg") as $plugin_name)
		{
			$res[] = $ilPluginAdmin->getPluginObject(IL_COMP_SERVICE,
				"Calendar", "capg", $plugin_name);
		}

		return $res;
	}

	/**
	 * @param $a_cal_entry
	 * @param $a_start_date
	 * @param $a_title
	 * @return string
	 */
	public function getContentByPlugins($a_cal_entry, $a_start_date, $a_title)
	{
		$content = $a_title;

		//demo of plugin execution.
		foreach($this->getActivePlugins() as $plugin)
		{
			$plugin->setAppointment($a_cal_entry, $a_start_date);
			if($new_content = $plugin->replaceContent())
			{
				$content = $new_content;
			}
			else
			{
				if($new_title = $plugin->editShyButtonTitle())
				{
					$content = $this->getAppointmentShyButton($a_cal_entry, $a_start_date, $new_title);
				}

				if($glyph = $plugin->addGlyph())
				{
					$content = $glyph." ".$content;
				}

				if($more_content = $plugin->addExtraContent())
				{
					$content .= " ".$more_content;
				}
			}
		}

		return $content;
	}

}