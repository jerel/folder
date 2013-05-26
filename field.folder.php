<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
 * Folder Field Type
 *
 * @author  Jerel Unruh
 * @package PyroCMS\Addon\FieldType
 */
class Field_folder
{
    public $field_type_name     = 'Folder';
    public $field_type_slug     = 'folder';
    public $db_col_type         = 'int';
    public $version             = '1.0';
    public $author              = array('name' => 'Jerel Unruh', 'url' => 'http://jerel.co');
    public $custom_parameters   = array('folder', 'create_folder');

    protected $temp_folder_id;

    /**
    * Event
    *
    * Append metadata
    *
    * @access  public
    * @return  void
    */
    public function event($field)
    {
        ci()->load->library('files/files');

        $folder_id = $this->_create_id($field);

        $allowed_extensions = array();
        foreach (config_item('files:allowed_file_ext') as $type) 
        {
            $allowed_extensions = array_merge($allowed_extensions, $type);
        }

        // extract the permissions that make no sense in this use case
        $permissions = json_encode(
            array_values(
                array_diff(
                    Files::allowed_actions(), array(
                        'create_folder', 
                        'edit_folder', 
                        'delete_folder'
                        )
                    )
                )
            );

        ci()->template->append_metadata(
            "<script>
                pyro.lang.fetching = '".lang('files:fetching')."';
                pyro.lang.fetch_completed = '".lang('files:fetch_completed')."';
                pyro.lang.start = '".lang('files:start')."';
                pyro.lang.width = '".lang('files:width')."';
                pyro.lang.height = '".lang('files:height')."';
                pyro.lang.ratio = '".lang('files:ratio')."';
                pyro.lang.full_size = '".lang('files:full_size')."';
                pyro.lang.cancel = '".lang('buttons:cancel')."';
                pyro.lang.synchronization_started = '".lang('files:synchronization_started')."';
                pyro.lang.untitled_folder = '".lang('files:untitled_folder')."';
                pyro.lang.exceeds_server_setting = '".lang('files:exceeds_server_setting')."';
                pyro.lang.exceeds_allowed = '".lang('files:exceeds_allowed')."';
                pyro.files = { permissions : ".$permissions." };
                pyro.files.max_size_possible = '".Files::$max_size_possible."';
                pyro.files.max_size_allowed = '".Files::$max_size_allowed."';
                pyro.files.valid_extensions = '".implode('|', $allowed_extensions)."';
                pyro.lang.file_type_not_allowed = '".addslashes(lang('files:file_type_not_allowed'))."';
                pyro.lang.new_folder_name = '".addslashes(lang('files:new_folder_name'))."';
                pyro.lang.alt_attribute = '".addslashes(lang('files:alt_attribute'))."';

                pyro.files.initial_folder_contents = ".(int)$folder_id.";
            </script>");

        Asset::add_path('files_module', APPPATH.'modules/files/');

        ci()->template
            ->append_css('files_module::jquery.fileupload-ui.css')
            ->append_css('jquery/jquery.tagsinput.css')
            ->append_css('files_module::files.css')
            ->append_js('files_module::jquery.fileupload.js')
            ->append_js('files_module::jquery.fileupload-ui.js')
            ->append_js('jquery/jquery.tagsinput.js')
            ->append_js('files_module::functions.js');

        ci()->type->add_css('folder', 'files_override.css');
    }

    // --------------------------------------------------------------------------

    /**
     * Process before saving to the database
     *
     * @access  public
     * @param   array
     * @return  string
     */
    public function pre_save($id, $field)
    {
        ci()->load->model('files/file_folders_m');

        // get the existing record
        $folder = ci()->file_folders_m->get($id);

        // make sure it's unique, even if they edited it manually
        $slug = $this->_unique_slug($folder->slug);

        ci()->file_folders_m->update($id, array(
            'hidden' => 0, 
            'parent_id' => $field->field_data['folder'],
            'slug' => $slug,
            'name' => $slug,
            )
        );

        return $id;
    }

    // --------------------------------------------------------------------------

    /**
     * Process before deleting the entry
     *
     * @access  public
     * @param   array
     * @return  string
     */
    public function entry_destruct($data, $field)
    {
        ci()->load->library('files/files');

        $folder_id = $data->{$field->field_slug};

        // if folders are created automatically then delete them.
        // Otherwise if they are shared don't. We don't want a page
        // deleting the folder that all pages using that layout depend on
        if ($field->field_data['create_folder'] and $folder_id)
        {
            $files = ci()->file_m
                ->where('folder_id', $folder_id)
                ->get_all();

            foreach ($files as $file)
            {
                Files::delete_file($file->id);
            }

            ci()->file_folders_m->delete($folder_id);
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
    * Output the form
    *
    * @param   array
    * @param   array
    * @return  string
    */
    public function form_output($data)
    {
        ci()->load->library('files/files');

        // the proper ID is always worked out in the event method, use that
        $data['value'] = $this->temp_folder_id;

        $data['folders']        = ci()->file_folders_m->count_by('parent_id', 0);
        $data['locations']      = array_combine(Files::$providers, Files::$providers);
        $data['folder_tree']    = Files::folder_tree();
        $data['folder_id']      = $data['value'];

        return ci()->load->view('files/admin/index', $data, true).
            form_hidden($data['form_slug'], (int)$data['value']);
    }

    // --------------------------------------------------------------------------

    /**
    * Build Folder List
    *
    * @access public
    * @return string
    */
    public function param_folder()
    {
        $dropdown = array();
        ci()->load->library('files/files');

        $folders = ci()->file_folders_m->get_folders();

        if ( ! $folders)
        {
            return '<em>'.lang('streams:folder.no_folder_warning').'</em>';
        }

        foreach ($folders as $id => $folder)
        {
            $dropdown[$id] = $folder->name;
        }

        return array(
            'instructions' => lang('streams:folder.folder_instructions'),
            'input' => form_dropdown('folder', $dropdown)
        );
    }

    // --------------------------------------------------------------------------

    /**
    * Create a new folder for this stream 
    * entry or manage the parent
    *
    * @access public
    * @return string
    */
    public function param_create_folder()
    {
        return array(
            'instructions' => lang('streams:folder.create_folder_instructions'),
            'input' => form_dropdown('create_folder', array(0 => lang('global:no'), 1 => lang('global:yes')), 1)
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Process before outputting for the plugin
     *
     * @access  public
     * @param   string
     * @param   array
     * @return  array
     */
    public function pre_output_plugin($input, $params)
    {
        $folder_id = $input ? $input : $params['folder'];
        ci()->load->model('files/file_m');

        return ci()->file_m
            ->where('folder_id', $folder_id)
            ->order_by('sort')
            ->get_all();
    }

    // --------------------------------------------------------------------------

    private function _create_id($field)
    {
        // clean up any temp folders that have been there over a day
        ci()->file_folders_m
            ->where('date_added <', now() - 86400)
            ->delete_by('parent_id', -1);

        if ( ! $field->value)
        {
            // default to using the parent folder that they selected when creating the field
            if ($field->field_data['folder'])
            {
                $this->temp_folder_id = $field->field_data['folder'];
            }

            // if they selected the automatic folder option then create one
            if ($field->field_data['create_folder'])
            {
                // create a hidden folder with no parent that we 
                // can clean up later if it's never used
                $result = Files::create_folder(-1, 'temp-'.$field->field_slug, 'local', '', 1);
                $this->temp_folder_id = $result['data']['id'];
            }
        }
        else
        {
            // validation failed or we are editing, use the proper ID
            $this->temp_folder_id = $field->value;
        }

        return $this->temp_folder_id;
    }

    // --------------------------------------------------------------------------

    private function _unique_slug($slug)
    {
        $i = '';
        $original_slug = $slug = str_replace('temp-', '', $slug);

        while (ci()->file_folders_m->count_by('slug', $slug))
        {
            $i++;
            $slug = $original_slug.'-'.$i;
        }

        return $slug;
    }
}
