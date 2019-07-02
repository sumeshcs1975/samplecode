<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Stock_taking extends CI_Controller {

	/**
	 * [$group description]
	 * @var [type]
	 */
	private $group;

	/**
	 * [$current_user description]
	 * @var [type]
	 */
	public $current_user;

	/**
	 * [__construct description]
	 */
	function __construct()
	{
		$router =& load_class('Router', 'core'); 
		$methode =  $router->fetch_method(); 

		if($methode == 'stock_update_scheduler' && !isset($_SERVER['REMOTE_ADDR'])){
			$_SERVER['REMOTE_ADDR'] = '';
		}
		parent::__construct();
		$this->session->set_userdata('referred_from', current_url());


		$this->load->library('encrypt');
		$methode =  $router->fetch_method();

		$this->js_path();
		$this->load->library('email');
		$this->load->library('ion_auth');
		#if($methode != 'supplier_stat' && $methode != 'email_img' && $methode != 'stock_update_scheduler'){
			if (!$this->ion_auth->logged_in()) redirect('auth/login', 'refresh');

			$this->current_user = get_current_user_data();
			$this->data['current_user'] = $this->current_user;

		#}
		$this->load->model('Stock_taking_Model');
		$this->load->model('Material_transfer_Model');
		$this->load->model('Bcsettings');
	}

	/**
	 * The landing page for Stock taking
	 * @author Sumesh
	 * @access public
	 * @return mixed
	 */
	public function index()
	{

		try {

			if(!$this->current_user->is_super)
				throw new Exception ('error_insufficient_rights_to_access');

			$this->carabiner->css( array(
				array('material/global/plugins/datatables/plugins/bootstrap/dataTables.bootstrap.css'),
				array('material/global/plugins/bootstrap-fileinput/bootstrap-fileinput.css'),
				array('material/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css'),
				array('material/global/plugins/select2-4.0.0/css/select2.css')
				) );

			$this->carabiner->js( array(
				array('material/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.js'),
				array('material/global/plugins/bootstrap-daterangepicker/moment.min.js'),
				array('material/global/plugins/jquery.form.min.js'),
				array('material/global/plugins/jquery-validation/js/jquery.validate.min.js'),
				array('material/global/plugins/bootstrap-select/bootstrap-select.min.js'),
				array('material/global/plugins/select2-4.0.0/js/select2.min.js'),
				array('material/global/plugins/bootbox/bootbox.min.js'),
				array('material/global/plugins/datatables/media/js/jquery.dataTables.min.js'),
				array('material/global/plugins/datatables/plugins/bootstrap/dataTables.bootstrap.js'),
				array('material/global/scripts/datatable.js'),
				array('material/global/plugins/autoNumeric.js'),
				array('pages/stock_taking/manage_st.js'),
				) );

			#Load js vars - Begins
			$js_vars['lang_please_correct'] = lang('please_correct');
			$js_vars['lang_saved'] = lang('saved');
			$js_vars['lang_warehouse'] = lang('warehouse');
			$js_vars['lang_st_del_warning'] = lang('st_del_warning');
			$js_vars['lang_confirm_start'] = lang('confirm_start');
			$js_vars['lang_confirm_finish'] = lang('confirm_finish');
			$js_vars['lang_all'] = lang('all');

			$js_vars['lang_select_material'] = lang('select_material');
			$js_vars['lang_enter_valid_qty'] = lang('enter_valid_qty');
			$js_vars['lang_existing_qty'] = lang('existing_qty');
			$js_vars['lang_saved'] = lang('saved');
			$js_vars['lang_add_material'] = lang('add_material');
			$js_vars['lang_cancelled'] = lang('cancelled');
			$js_vars['path_show_existing_qty'] =  base_url('stock_taking/show_existing_qty');
			$js_vars['path_save_note'] =  base_url('stock_taking/save_note');
			$js_vars['path_show_stprogres_dt'] =  base_url('stock_taking/show_stprogres_dt');
			$js_vars['path_matdata'] =  base_url('material_transfer/material_data');

			$js_vars['path_incharge'] =  base_url('material_transfer/emp_data/'.'1-2');
			$js_vars['path_location'] =  base_url('material_transfer/emp_data/');
			$js_vars['path_show_st_dt'] =  base_url('stock_taking/show_st_dt');
			$js_vars['path_get_st_data'] =  base_url('stock_taking/get_st_data');
			$js_vars['act_url'] =  base_url('stock_taking/act');
			$js_vars['path_show_report'] =  base_url('stock_taking/show_report');
			$js_vars['path_show_report_dt'] =  base_url('stock_taking/show_report_dt');
			$js_vars['path_do_st'] =  base_url('stock_taking/do_st');
			$js_vars['path_save_qty'] =  base_url('stock_taking/save_qty');
			$js_vars['st_prn_bae_path'] =  base_url('stock_taking/print_st_report/');
			$js_vars['path_add_new_material'] =  base_url('stock_taking/add_new_material/');
			$js_vars['path_start_and_launch'] =  base_url('stock_taking/start_and_launch/');

			$this->data['jspath'] .= "\n var stkjs = ".json_encode($js_vars)."\n";
			#Load js vars - Ends

			$this->data['title'] = "stock_taking";
			$this->data['menu']['main'] = "material";
			$this->data['menu']['sub'] = "stock-taking";

			$this->data['group'] = $this->current_user->group_name;


			$this->load->view('pages/hh', $this->data);
			$this->load->view('pages/stock_taking/st', $this->data);
			$this->load->view('pages/ff', $this->data);

		} catch (Exception $e) {

			$this->_response = ['response' => 'error', 'msg' => $e->getMessage()];
			throwException($this->_response);
		}
		
		
	}

	/**
	 * Launch stock taking form
	 * @author Sumesh
	 * @access public
	 * @param  int  $id stock taking id
	 * @return mixed
	 */
	public function do_st()
	{
		$error_messages = '';
		$messages = '';
		$location = '';
		$note2 = '';
		$st_title = '';

		$id = $this->input->post('id');
		$existing_data = $this->Stock_taking_Model->get_st_row($id);

		if($existing_data === FALSE){
			append_msg($error_messages, lang('norec_found'));
		}else{
			$input_data = compact('id');
			$error_messages = $this->Stock_taking_Model->validate(0, $input_data, $existing_data);
			$st_title = $existing_data->title;
			$note2 = $existing_data->note2;

			if($existing_data->location > 0){
				$location = $existing_data->location_emp;
			}else{
				$location = lang('warehouse');
			}
		}


		$ret_data['status'] = 'OK';
		$ret_data['stock_taking_id'] = $id;
		$ret_data['error'] = $error_messages;
		$ret_data['note2'] = $note2;
		$ret_data['location'] = $location;
		$ret_data['st_title'] = $st_title;
		$this->output->set_content_type('application/json')->set_output( json_encode($ret_data) );
	}

	/**
	 * Get stock taking data
	 * @author Sumesh
	 * @access public
	 * @var  int  $id stock taking id
	 * @return mixed
	 */
	public function get_st_data()
	{

		$id = intval($this->input->post('id'));
		$error_messages = '';
		if($id > 0 ){
			$st_row = $this->Stock_taking_Model->get_st_row($id);
			if($st_row === FALSE){
				append_msg($error_messages, lang('norec_found'));
			}else{
				$obj_assigned_to = new stdClass();
				$obj_assigned_to->id = $st_row->assigned_to;
				$obj_assigned_to->text = $st_row->employee_name;
				$st_row->obj_assigned_to = $obj_assigned_to;

				$obj_location = new stdClass();
				$obj_location->id = $st_row->location;
				if($st_row->location == ST_LOCATION_WH){
					$obj_location->text = lang('warehouse');
				}else{
					$obj_location->text = $st_row->location_emp;
				}

				$st_row->obj_location = $obj_location;
			}
		}else{
			append_msg($error_messages, lang('invalid_id'));
		}

		$ret_data['status'] = 'OK';
		$ret_data['error'] = $error_messages;
		$ret_data['st_row'] = $st_row;
		$this->output->set_content_type('application/json')->set_output( json_encode($ret_data) );
	}

	/**
	 * Set js path
	 * @author Sumesh
	 * @access public
	 */	
	private function js_path()
	{
		$this->data['jspath'] = "var jsPath = {
					path_assets: \"".base_url('assets')."/\",
				}";
	}

	/**
	 * Save material quantity from stock taking form
	 * @author Sumesh
	 * @access public
	 * @return mixed
	 */
	public function save_qty(){
		$error_messages = '';
		$messages = '';

		$id = $this->input->post('stock_taking_id');
		$stdet_id = $this->input->post('stdet_id');
		$qty_real = floatval($this->input->post('qty_real'));
		$existing_data = $this->Stock_taking_Model->get_st_row($id);

		$input_data = compact("qty_real", "id");
		$error_messages = $this->Stock_taking_Model->validate(1, $input_data, $existing_data);
		if(!$error_messages){
			$err = $this->Stock_taking_Model->save_input_qty($stdet_id, $qty_real);

			if($err){
				append_str($error_messages,  $err, "<br>");
			}
		}
		$ret_data['status'] = 'OK';
		$ret_data['error'] = $error_messages;
		$ret_data['msg'] = $messages;
		$this->output->set_content_type('application/json')->set_output( json_encode($ret_data) );
	}

	/**
	 * Show existing quantity of a material during a stock taking session
	 * @author Sumesh
	 * @access public
	 * @return mixed
	 */
	public function show_existing_qty(){
		$error_messages = '';
		$existing_qty = $qty_real = 0;

		$material_id = $this->input->get('material_id');
		$id = intval($this->encrypt->decode(safe_b64decode($this->input->get('c'))));

		$st_row = $this->Stock_taking_Model->get_st_row($id);
		if($st_row !== FALSE){
			$st_data = $this->Stock_taking_Model->get_st_det($id, $material_id);
			if($st_data !== FALSE){
				if($st_row->location == ST_LOCATION_WH){
					$existing_qty = $st_data->existing_qty;
				}else{
					$existing_qty = $st_data->qty_sys;
				}

				$qty_real = $st_data->qty_real;
			}else{
				if($st_row->location == ST_LOCATION_WH){
					$material_data = $this->Material_transfer_Model->get_material($material_id);
					if($material_data !== FALSE){
						$existing_qty = $material_data->qty;
					}else{
						append_str($error_messages,  lang('norec_found'), "<br>");
					}
				}else{
					$existing_qty = 0;
				}
			}
		}else{
			append_str($error_messages,  lang('invalid_id'), "<br>");
		}

		$ret_data['status'] = 'OK';
		$ret_data['error'] = $error_messages;
		$ret_data['existing_qty'] = $existing_qty;
		$ret_data['qty_real'] = $qty_real;
		$this->output->set_content_type('application/json')->set_output( json_encode($ret_data) );
	}

	/**
	 * Save comment filed during stocktaking
	 * @author Sumesh
	 * @access public
	 * @return mixed
	 */
	public function save_note(){
		$error_messages = '';
		$note2 = $this->input->post('note2');
		$id = intval($this->input->post('stock_taking_id'));
		if(!$id){
			append_str($error_messages,  lang('invalid_id'), "<br>");
		}else if(!$note2){
			append_str($error_messages,  lang('enter_note'), "<br>");
		}else{
			$ret_val = $this->Stock_taking_Model->update_st(array('id' => $id, 'note2' => $note2));
		}

		$ret_data['status'] = 'OK';
		$ret_data['error'] = $error_messages;
		$this->output->set_content_type('application/json')->set_output( json_encode($ret_data) );
	}

	 /**
	 * Add or Modify Stocktaking record
	 * @author Sumesh
	 * @access public
	 * @return mixed
	 */
	public function update_st_data()
	{

		$error_messages = '';
		$this->load->library(array('form_validation'));

		$this->form_validation->set_rules('title', 'Title', 'trim|required');
		$this->form_validation->set_rules('st_date', 'Date', 'trim|required');
		$this->form_validation->set_rules('assigned_to', 'Assigned to', 'trim|required');
		$this->form_validation->set_rules('location', 'Location', 'trim|required');


		if ($this->form_validation->run() == true)
		{
			if(is_submitted()){
				$id = intval($this->input->post('is_update'));

				if($id){
					$st_data = $this->Stock_taking_Model->get_st_row($id);
					if($st_data === FALSE){
						append_msg($error_messages, lang('invalid_id'));
					}else{
						if($st_data->status == ST_STATUS_STARTED && $st_data->location != $this->input->post('location')){
							append_msg($error_messages, lang('cant_change_loc_st_started'));
						}else if($st_data->status == ST_STATUS_FINISHED && $st_data->location != $this->input->post('location')){
							append_msg($error_messages, lang('cant_change_loc_st_finished'));
						}
					}
				}

				if(!$error_messages){
					$data = array(
					'id' => $id,
					'title' => $this->input->post('title'),
					'st_date' => $this->input->post('st_date'),
					'location' => $this->input->post('location'),
					'assigned_to' => $this->input->post('assigned_to'),
					'note' => $this->input->post('note'),
					'note2' => $this->input->post('note2'));

					list($success, $id) = $this->Stock_taking_Model->update_st($data);

					if(!$success){
						append_msg($error_messages, 'Unexpected error - update_st_data()');
					}
				}
			}else{
				append_msg($error_messages, 'Invalid request type - update_st_data()');
			}
		}else{
			append_msg($error_messages, validation_errors('', "\n"));
		}
		$ret_data['status'] = 'OK';
		$ret_data['error'] = $error_messages;
		$this->output->set_content_type('application/json')->set_output( json_encode($ret_data) );
	}

	/**
	 * Do various actions (delete, start, finish) on stock-taking row
	 * @author Sumesh
	 * @access public
	 * @var $id is the stock-taking id
	 * @var string $type is the action name
	 * @return mixed
	 */
	public function act()
	{

		$id = intval($this->input->post('id'));
		$type = $this->input->post('type');
		$error_messages = '';
		$msg = '';

		if($id > 0 ){
			$st_data = $this->Stock_taking_Model->get_st_row($id);
			if($st_data === FALSE){
				append_msg($error_messages, lang('invalid_id'));
			}else{
				if($type == 'destroy'){
					/*
					$st_data->status == ST_STATUS_FINISHED
					$st_data->status == ST_STATUS_FINISHED
					*/
					$ret_stat = $this->Stock_taking_Model->delete($id);
					if(!$ret_stat){
						append_msg($error_messages, lang('del_failed'));
					}else{
						$msg = lang('record_deleted');
					}
				}else if($type == 'start'){
					if($st_data->status == ST_STATUS_PAUSED){
						$ret_stat = $this->Stock_taking_Model->do_action($st_data, 'start');
						if(!$ret_stat){
							append_msg($error_messages, lang('unexpected_err'));
						}
					}else{
						append_msg($error_messages, lang('already_started'));
					}
				}else if($type == 'finish'){
					if($st_data->status == ST_STATUS_STARTED){
						$ret_stat = $this->Stock_taking_Model->do_action($st_data, 'finish');
						if(!$ret_stat){
							append_msg($error_messages, lang('unexpected_err'));
						}
					}else{
						if($st_data->status == ST_STATUS_FINISHED){
							append_msg($error_messages, lang('already_finished'));
						}else{
							append_msg($error_messages, lang('not_yet_started'));
						}
					}
				}

			}
		}else{
			append_msg($error_messages, lang('invalid_id'));
		}

		$ret_data['status'] = 'OK';
		$ret_data['msg'] = $msg;
		$ret_data['error'] = $error_messages;
		$this->output->set_content_type('application/json')->set_output( json_encode($ret_data) );
	}

	/**
	 * Fetch list of items that are undergoing stock-taking. Used by DataTable control (When user clicks Launch stock taker)
	 * @author Sumesh
	 * @access public
	 * @return mixed
	 */
	public function show_stprogres_dt()
	{
		$column_list = array(
			0 => 'mat_name',
			1 => 'qty_real',
			2 => 'qty_sys',
			3 => 'diff2',
			4 => 'last_scanned_on'
		);
	
		$search_data = $this->input->post('search_data');
		$search_params = array();
		if(is_array($search_data)){
			foreach($search_data as $sdata) {
				$search_params[$sdata['name']] = $sdata['value'];
			}
		}
		#print_r($search_params);exit;
		
		$order_column = $column_list[intval($this->input->post('order')[0]['column'])];
		$order_dir = $this->input->post('order')[0]['dir'];
		$limit = intval($this->input->post('length'));
		$start = intval($this->input->post('start'));
		$iDisplayLength = intval($this->input->post('length'));
		$iDisplayStart = intval($this->input->post('start'));
		$sEcho = intval($this->input->post('draw'));

		list($stprogres_rows, $iTotalRecords) = $this->Stock_taking_Model->get_stprogres_dt($limit, $start, $order_column, $order_dir, $search_params);
		
		$iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength; 
		$end = $iDisplayStart + $iDisplayLength;
		$end = $end > $iTotalRecords ? $iTotalRecords : $end;
		
		$records = array();
		$records["data"] = array(); 

		
		foreach($stprogres_rows as $stprogres_row) {
			$qty_real = $stprogres_row->qty_real + 0;
			if($qty_real == 0){
				$qty_real = '';
			}

			if($stprogres_row->allow_decimal == 1){
				$extra_autonumeric_data_tags = 'data-a-sep="" data-m-dec="1" data-v-min="0" data-v-max="999999"';
			}else{
				$extra_autonumeric_data_tags = 'data-a-sep="" data-v-min="0" data-v-max="999999"';
			}

			$count_input = '<input ' .$extra_autonumeric_data_tags. ' data-id="'.$stprogres_row->stdetid.'" type="text" name="qty_real_'.$stprogres_row->stdetid.'"  id="qty_real_'.$stprogres_row->stdetid.'"  value="'.$qty_real.'" class="qty_real form-control" placeholder="" style="width:80px !important; display:inline !important">&nbsp;<a id="btn_update_qty_real'.$stprogres_row->stdetid.'" data-id="'.$stprogres_row->stdetid.'" href="javascript:;" class="btn btn-circle btn-xs blue cls_qty_real"><i class="fa fa-save"></i></a>';
		    $records["data"][] = array(
		      $stprogres_row->mat_name,
		      $count_input,
		      $stprogres_row->qty_sys + 0,
		      (intval($stprogres_row->last_scanned_on)?($stprogres_row->dif + 0):''),
		      (intval($stprogres_row->last_scanned_on)?$stprogres_row->last_scanned_on:'')
		    );
		}

		$records["draw"] = $sEcho;
		$records["recordsTotal"] = $iTotalRecords;
		$records["recordsFiltered"] = $iTotalRecords;
		  
		$this->output->set_content_type('application/json')->set_output( json_encode($records) );
	}

	/**
	 * Fetch list of items in stock-taking. Used by DataTable control (When user clicks Show report)
	 * @author Sumesh
	 * @access public
	 * @return mixed
	 */
	public function show_report_dt()
	{
		$column_list = array(
			0 => 'mat_name',
			1 => 'qty_real',
			2 => 'qty_sys',
			3 => 'diff_type'
		);
	
		$search_data = $this->input->post('search_data');
		$search_params = array();
		if(is_array($search_data)){
			foreach($search_data as $sdata) {
				$search_params[$sdata['name']] = $sdata['value'];
			}
		}
		#print_r($search_params);exit;
		
		$order_column = $column_list[intval($this->input->post('order')[0]['column'])];
		$order_dir = $this->input->post('order')[0]['dir'];
		$limit = intval($this->input->post('length'));
		$start = intval($this->input->post('start'));
		$iDisplayLength = intval($this->input->post('length'));
		$iDisplayStart = intval($this->input->post('start'));
		$sEcho = intval($this->input->post('draw'));

		list($stprogres_rows, $iTotalRecords) = $this->Stock_taking_Model->get_report_dt($limit, $start, $order_column, $order_dir, $search_params);
		
		$iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength; 
		$end = $iDisplayStart + $iDisplayLength;
		$end = $end > $iTotalRecords ? $iTotalRecords : $end;
		
		$records = array();
		$records["data"] = array(); 

		
		foreach($stprogres_rows as $stprogres_row) {
			$qty_real =  $stprogres_row->qty_real + 0;
			$qty_sys = $stprogres_row->qty_sys + 0;
			if($qty_real == 0 && !intval($stprogres_row->last_scanned_on)){
				$qty_real = '';
			}

		    $records["data"][] = array(
		      $stprogres_row->mat_name,
		      $qty_real,
		      $qty_sys,
		     (intval($stprogres_row->last_scanned_on)?($stprogres_row->dif + 0):''),
		    );
		}

		$records["draw"] = $sEcho;
		$records["recordsTotal"] = $iTotalRecords;
		$records["recordsFiltered"] = $iTotalRecords;
		  
		$this->output->set_content_type('application/json')->set_output( json_encode($records) );
	}

	/**
	 * Fetch list of stock-taking rows. Used by DataTable control (In stock-taking landing page)
	 * @author Sumesh
	 * @access public
	 * @return mixed
	 */
	public function show_st_dt()
	{
		$column_list = array(
			0 => 'title',
			1 => 'st_date',
			2 => 'location_emp',
			3 => 'assigned_to_emp'
		);

		$search_params = array();
		/*
		$search_data = $this->input->post('search_data');
		$search_params = array();
		if(is_array($search_data)){
			foreach($search_data as $sdata) {
				$search_params[$sdata['name']] = $sdata['value'];
			}
		}
		*/
		#print_r($search_params);exit;
		
		$order_column = $column_list[intval($this->input->post('order')[0]['column'])];
		$order_dir = $this->input->post('order')[0]['dir'];
		$limit = intval($this->input->post('length'));
		$start = intval($this->input->post('start'));
		$iDisplayLength = intval($this->input->post('length'));
		$iDisplayStart = intval($this->input->post('start'));
		$sEcho = intval($this->input->post('draw'));

		list($st_rows, $iTotalRecords) = $this->Stock_taking_Model->get_st_dt($limit, $start, $order_column, $order_dir, $search_params);
		
		$iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength; 
		$end = $iDisplayStart + $iDisplayLength;
		$end = $end > $iTotalRecords ? $iTotalRecords : $end;
		
		$records = array();
		$records["data"] = array(); 

		
		foreach($st_rows as $st_row) {

			if($st_row->location == ST_LOCATION_WH){
				$loc = lang('warehouse');
			}else{
				$loc = $st_row->location_emp;
			}

			$status_icon = '';
			$stat_change_button = '';
			$extra_buttons = '';



			$extra_buttons .= '<a class="btn default s_btn btn-xs blue cls_show_streport"   data-id="'.$st_row->id.'"  href="javascript:;" rel="tooltip" data-original-title="'.lang('show_report'). '"><i class="fa  fa-search" ></i></a> ';

			if($st_row->status == ST_STATUS_PAUSED){
				$status_icon = "<i class='fa fa-exclamation' rel='tooltip' data-original-title='".lang('not_started'). "'></i>";
				$stat_change_button = '<a class="btn s_btn default btn-xs green cls_start_and_launch" data-id="'.$st_row->id.'" data-grid="st"  href="javascript:;" rel="tooltip" data-original-title="'.lang('clik_to_start'). '"><i class="fa fa-play" ></i></a> ';
			}else if($st_row->status == ST_STATUS_STARTED){
				$status_icon = "<i class='fa fa-pencil' rel='tooltip' data-original-title='".lang('started'). ' - '  .$st_row->started_on."'></i>";
				$stat_change_button = '<a class="btn s_btn default btn-xs green act-data" data-id="'.$st_row->id.'" data-grid="st" data-field="finish" href="javascript:;" rel="tooltip" data-original-title="'.lang('clik_to_finish'). '"><i class="fa  fa-check-square-o" ></i></a> ';
				$extra_buttons .= '<a class="btn s_btn default btn-xs green cls_count_stock"   data-id="'.$st_row->id.'"  href="javascript:;" rel="tooltip" data-original-title="'.lang('launch_stock_taker'). '"><i class="fa  fa-play" ></i></a> ';

			}else  if($st_row->status == ST_STATUS_FINISHED){
				$status_icon = "<i class='fa fa-thumbs-o-up' rel='tooltip' data-original-title='".lang('finished'). ' - '  .$st_row->finished_on."'></i>";
			}

			if($st_row->status == ST_STATUS_STARTED || $st_row->status == ST_STATUS_FINISHED){

				$prn_url = base_url('stock_taking/print_stlist/'.$st_row->id);
				$extra_buttons .= '<a target="_blank" class="btn s_btn default btn-xs white" href="'.$prn_url.'" rel="tooltip" data-original-title="'.lang('print_material_list'). '"><i class="fa fa-print"></i></a> ';
			}


			$action_buttons = '<a class="btn btn-xs s_btn btn-primary update-data" data-id="'.$st_row->id.'" data-grid="st"  href="javascript:;" rel="tooltip" data-original-title="'.lang('modify'). '"><i class="fa fa-pencil"></i></a>
			<a class="btn btn-xs s_btn btn-danger act-data" data-id="'.$st_row->id.'" data-grid="st" data-field="destroy" href="javascript:;" rel="tooltip" data-original-title="'.lang('delete'). '"><i class="fa fa-trash-o" ></i></a> ';
		    $records["data"][] = array(
		      $st_row->title . ' ' . $status_icon,
		      $st_row->st_date,
		      $loc,
		      $st_row->assigned_to_emp,
			  $action_buttons . $stat_change_button.$extra_buttons
		    );
		}

		$records["draw"] = $sEcho;
		$records["recordsTotal"] = $iTotalRecords;
		$records["recordsFiltered"] = $iTotalRecords;
		  
		$this->output->set_content_type('application/json')->set_output( json_encode($records) );
	}

	/**
	 * Show stock taking report
	 * @author Sumesh
	 * @access public
	 * @var  int  $id Stock-taking id
	 * @return mixed
	 */
	public function show_report(){
		$error_messages = '';
		$msg = '';
		$id = intval($this->input->post('id'));
		$st_data = $this->Stock_taking_Model->get_st_row($id);
		$print_url = $html = '';
		if($st_data !== FALSE){
			$print_url = base_url('stock_taking/print_stlist/'.$id);
			$ret_data['st_data'] = $this->data['st_data'] = $st_data;
			$this->data['print_url'] = $print_url;
			list($mismatch_count, $not_scanned_count) = $this->Stock_taking_Model->get_st_summary($id);
			$this->data['mismatch_count'] = $mismatch_count;
			$this->data['not_scanned_count'] = $not_scanned_count;
			$ret_data['html'] = $this->load->view('pages/stock_taking/report_body.php', $this->data, true);
		}else{
			append_msg($error_messages, lang('invalid_id'));
		}

		$ret_data['status'] = 'OK';
		$ret_data['print_url'] =  $print_url;
		$ret_data['msg'] = $msg;
		$ret_data['error'] = $error_messages;
		$this->output->set_content_type('application/json')->set_output( json_encode($ret_data) );
	}

	/**
	 * Show stock taking details as pdf
	 * @author Sumesh
	 * @access public
	 * @param  int  $id Stock-taking id
	 * @return mixed
	 */
	public function print_stlist($id=''){
		$st_data = $this->Stock_taking_Model->get_st_row($id);
		if($st_data !== FALSE){
			if($st_data->location == ST_LOCATION_WH){
				$loc = lang('warehouse');
			}else{
				$loc = $st_data->location_emp;
			}
			$this->load->helper('pdf_helper');
			tcpdf();
			$obj_pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
			$obj_pdf->SetCreator(PDF_CREATOR);
			$title =  $st_data->title;
			$obj_pdf->SetTitle($st_data->title);
			$obj_pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, $title, lang('location2').':'.$loc);
			$obj_pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
			$obj_pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
			$obj_pdf->SetDefaultMonospacedFont('helvetica');
			$obj_pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
			$obj_pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
			$obj_pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
			$obj_pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
			$obj_pdf->SetFont('helvetica', '', 9);
			$obj_pdf->setFontSubsetting(false);
			$obj_pdf->AddPage();
			ob_start();
			$this->db->select('m.name, qty_sys', FALSE);
			$this->db->from('stock_taking_det stdet');
			$this->db->join('materials m', 'stdet.material_id = m.id', 'left');
			$this->db->where('stdet.stock_taking_id', $id);
			$this->db->order_by('m.name', 'ASC');
			$query = $this->db->get();
			?>
			<table cellspacing="0" cellpadding="2" border="1" width="100%">
			<?php
				foreach($query->result() as $m_row) {
			?>
			<tr>
				<td style="width:40%;"><?php echo $m_row->name ?> (<?php echo $m_row->qty_sys + 0 ?>)</td>
				<td style="width:60%;"></td>
			</tr>
			<?php
				}
			?>
			<?php
				for ($ix = 0; $ix <= 30; $ix++) { 
			?>
			<tr>
				<td style="width:40%;"></td>
				<td style="width:60%;"></td>
			</tr>
			<?php
				}
			?>
			</table>
			<?php
			$content = ob_get_contents();
			ob_end_clean();
			$obj_pdf->writeHTML($content, true, false, true, false, '');
			$obj_pdf->Output('output.pdf', 'I');
		}
	}

	/**
	 * Show stock taking report as pdf
	 * @author Sumesh
	 * @access public
	 * @param  int  $id Stock-taking id
	 * @return mixed
	 */
	public function print_st_report($id=''){
		$st_data = $this->Stock_taking_Model->get_st_row($id);
		if($st_data !== FALSE){
			if($st_data->location == ST_LOCATION_WH){
				$loc = lang('warehouse');
			}else{
				$loc = $st_data->location_emp;
			}
			$this->load->helper('pdf_helper');
			tcpdf();
			$obj_pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
			$obj_pdf->SetCreator(PDF_CREATOR);
			$title =  $st_data->title;
			$obj_pdf->SetTitle($title);
			$obj_pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, $title, lang('location2').':'.$loc."\n".lang('printed_on') . ':'.cur_dt());
			$obj_pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
			$obj_pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
			$obj_pdf->SetDefaultMonospacedFont('helvetica');
			$obj_pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
			$obj_pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
			$obj_pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
			$obj_pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
			$obj_pdf->SetFont('helvetica', '', 9);
			$obj_pdf->setFontSubsetting(false);
			$obj_pdf->AddPage();
			ob_start();

			$this->db->select('m.name as mat_name, 
				stdet.qty_real,
				stdet.qty_sys,
				stdet.qty_real - stdet.qty_sys as dif,
				stdet.last_scanned_on', FALSE);

				$this->db->from('stock_taking_det stdet');
				$this->db->join('materials m', 'stdet.material_id = m.id', 'left');
				$this->db->where('stdet.stock_taking_id', $id);
				$this->db->order_by('m.name', 'ASC');


			$query = $this->db->get();
			list($mismatch_count, $not_scanned_count) = $this->Stock_taking_Model->get_st_summary($id);
			?>
			<br>
				<div>
				<strong><?=lang('status');?> </strong>
				<?php
					if($st_data->status == ST_STATUS_PAUSED){
						echo lang('not_yet_started');
					}else if($st_data->status == ST_STATUS_STARTED){
						echo lang('started') . ' ' . lang('on') . ' ' . $st_data->started_on;
					}else if($st_data->status == ST_STATUS_FINISHED){
						
						echo lang('finished') . ' (' . lang('started_on') . ' ' . $st_data->started_on . ' ' . lang('finished_by') . ' ' . $st_data->finished_on .')';
					}
				?>
				</div>
				<?php if($st_data->note){?>
				<div>
					<strong><?=lang('note');?> </strong><br>
					<?php echo $st_data->note;?>
				</div>
				<?php } ?>
				<?php if($st_data->note2){?>
				<div>
					<strong><?=lang('comments_by_staff');?> </strong><br>
					<?php echo $st_data->note2;?>
				</div>
				<?php } ?>

				<div>
					<strong><?=lang('summary');?> </strong><br>
					<?php
					if($st_data->status == ST_STATUS_PAUSED){
						echo lang('stk_not_started').'<br>';
					}else if($st_data->status == ST_STATUS_STARTED){
						echo lang('stk_not_finished').'<br>'; 
					}
					if($st_data->status != ST_STATUS_PAUSED){
						if( $mismatch_count > 0){
							echo lang('mat_mismatch_found', $mismatch_count);
						}else{
							echo lang('mat_mismatch_found', $mismatch_count);
						}
						echo '<br>';
						if( $not_scanned_count > 0){
							echo lang('mat_not_scanned', $not_scanned_count);
						}
					}
					?>
				</div><br>	
			<table cellspacing="0" cellpadding="2" border="1" width="100%">
			<thead>
			<tr>
				<th>
					<?=lang('material');?>
				</th>
				<th>
					<?=lang('qty_counted');?>
				</th>
				<th >
					<?=lang('existing_qty');?>
				</th>
				<th >
					<?=lang('difference');?>
				</th>
			</tr>
			</thead>
			<?php
				foreach($query->result() as $stdet_row) {
					$qty_real =  $stdet_row->qty_real + 0;
					$qty_sys = $stdet_row->qty_sys + 0;
					if($qty_real == 0 && !intval($stdet_row->last_scanned_on)){
						$qty_real = '';
					}
			?>
			<tr>
				<td><?php echo $stdet_row->mat_name ?></td>
				<td><?php echo $qty_real ?></td>
				<td><?php echo $qty_sys ?></td>
				<td><?php echo (intval($stdet_row->last_scanned_on)?($stdet_row->dif + 0):'') ?></td>
			</tr>
			<?php
				}
			?>
			</table>
			<?php
			$content = ob_get_contents();
			ob_end_clean();
			$obj_pdf->writeHTML($content, true, false, true, false, '');
			$obj_pdf->Output('output.pdf', 'I');
		}
	}

	/**
	 * Add material to existing stock during stock-taking 
	 * @author Sumesh
	 * @access public
	 * @var  int  $material_id Material id
	 * @var  int  $stock_taking_id stock-taking id
	 * @return mixed
	 */
	public function add_new_material()
	{

		$id = intval($this->input->post('stock_taking_id'));
		$material_id = intval($this->input->post('material_id'));
		$error_messages = '';
		$msg = '';

		if($id > 0 ){
			if($material_id > 0 ){
				$st_data = $this->Stock_taking_Model->get_st_row($id);
				if($st_data === FALSE){
					list($ret_val, $err) = append_msg($error_messages, lang('invalid_id'));
					if($err){
						append_msg($error_messages, $err);
					}
				}else{
					$st_data = $this->Stock_taking_Model->add_new_material_to_stdet($id, $material_id, $st_data);
				}
			}else{
				append_msg($error_messages, lang('invalid_material_id'));
			}
		}else{
			append_msg($error_messages, lang('invalid_id'));
		}

		$ret_data['status'] = 'OK';
		$ret_data['msg'] = $msg;
		$ret_data['error'] = $error_messages;
		$this->output->set_content_type('application/json')->set_output( json_encode($ret_data) );
	}

	/**
	 * Begin stock-taking 
	 * @author Sumesh
	 * @access public
	 * @var  int  $id stock-taking id
	 * @return mixed
	 */
	public function start_and_launch()
	{
		$id = intval($this->input->post('id'));
		$error_messages = '';
		$msg = '';
		$location = '';
		$note2 = '';
		$st_title = '';

		if($id > 0 ){
			$st_data = $this->Stock_taking_Model->get_st_row($id);
			if($st_data === FALSE){
				append_msg($error_messages, lang('invalid_id'));
			}else{
				if($st_data->status == ST_STATUS_PAUSED){
					$ret_stat = $this->Stock_taking_Model->do_action($st_data, 'start');
					if(!$ret_stat){
						append_msg($error_messages, lang('unexpected_err').':start_and_launch()');
					}else{
						$st_data->status = ST_STATUS_STARTED;
						$input_data = compact('id');
						$error_messages = $this->Stock_taking_Model->validate(0, $input_data, $st_data);
						$st_title = $st_data->title;
						$note2 = $st_data->note2;

						if($st_data->location > 0){
							$location = $st_data->location_emp;
						}else{
							$location = lang('warehouse');
						}
					}
				}else{
					append_msg($error_messages, lang('already_started'));
				}
			}
		}else{
			append_msg($error_messages, lang('invalid_id'));
		}

		$ret_data['status'] = 'OK';
		$ret_data['stock_taking_id'] = $id;
		$ret_data['note2'] = $note2;
		$ret_data['location'] = $location;
		$ret_data['st_title'] = $st_title;
		$ret_data['msg'] = $msg;
		$ret_data['error'] = $error_messages;
		$this->output->set_content_type('application/json')->set_output( json_encode($ret_data) );
	}
}
