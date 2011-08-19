<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
						'pi_name'			=> 'Search Hilighter',
						'pi_version'		=> '1.3.1',
						'pi_author'			=> 'EllisLab Development Team',
						'pi_author_url'		=> 'http://www.expressionengine.com/',
						'pi_description'	=> 'Will Hilight Search Terms for incoming search',
						'pi_usage'			=> Search_hilite::usage()
					);


/**
 * Search Hilite Class
 *
 * @package			ExpressionEngine
 * @category		Plugin
 * @author			ExpressionEngine Dev Team
 * @copyright		Copyright (c) 2004 - 2009, EllisLab, Inc.
 * @link			http://expressionengine.com/downloads/details/search_hilite/
 */
class Search_hilite
{
	var $return_data	= '';
	var $supported 		= array('a9','dogpile', 'ee', 'google','lycos', 'yahoo');
	var $search_terms	= array();
	var $which 			= '';
	var $cache_name		= 'search_hilite';
	var $cache_path		= ''; // Path to cache file.
	var $write_cache	= 'n';  // Set to 'y' to write cache file
	var $cache_length	= 200;
	
	
	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	function Search_hilite($str = '',$param = '0')
	{
		$this->EE =& get_instance();
		
		$this->EE->load->helper('text');
		
		if ($str == '')
		{
        	$str = $this->EE->TMPL->tagdata;
        }
        
        $param = ( ! $this->EE->TMPL->fetch_param('welcome') || $this->EE->TMPL->fetch_param('welcome') != 'true') ? '0' : '1';
        $partial = ( ! $this->EE->TMPL->fetch_param('partial') || $this->EE->TMPL->fetch_param('partial') == 'true') ? 'y' : 'n';
        
        $ref = ( ! isset($_SERVER['HTTP_REFERER'])) ? '' : entities_to_ascii($this->EE->security->xss_clean($_SERVER['HTTP_REFERER']));
		
		if ($ref == '')
        {
            $this->return_data = ($param == '1') ? '' : $str;
            return;
        }
        else
        {
        	$ref = urldecode($ref);
        }
        
		// -------------------------
		// Determine Search Engine
		// -------------------------
		
		foreach ($this->supported as $search)
		{
			switch($search)
			{
				case 'a9'		:
					if (strpos($ref,'a9.com/') !== false)
					{
						$this->which = 'a9';
						
						$query = preg_replace('/^.*\//','',$ref);
						$this->search_terms = explode(' ',$query);
					}					
				break;
				case 'dogpile'	:
					if (strpos($ref,'dogpile.com/info.dogpl/search/') !== false)
					{
						$this->which = 'dogpile';
						
						if (strpos($ref, '?advanced') !== false)
						{
							$query = preg_replace('/^.*(q_all(.*))&q_not?.*$/i','$1',$ref);
							$search_qualifiers = preg_split("/&|=/",$query);
							
							foreach($search_qualifiers as $v)
							{
								if (substr($v,0,2) != 'q_')
								{
									$v = preg_replace('/\'|"/','',$v);
									$temp_items = explode('+',$v);
									$this->search_terms = array_merge($this->search_terms,$temp_items);
								}
							}							
						}
						else
						{
							$query = preg_replace('/^.*\//','',$ref);
							$this->search_terms = preg_split ("/[\s,\+\.]+/",$query);
						}
					}	
				break;
				case 'ee' :
					if (strlen($ref) >= 32)
        			{
						if ( ! isset($this->EE->uri->segments[count($this->EE->uri->segments)]))
						{
							continue;
						}
						else
       					{
							$this->EE->uri->query_string = $this->EE->uri->segments[count($this->EE->uri->segments)];
						}
        				
						if (strlen($this->EE->uri->query_string) == 32)
						{
							$search_id = $this->EE->uri->query_string;
						}
						else
						{
							$search_id = substr($this->EE->uri->query_string, 0, 32);
						}
        				
						$this->EE->db->select('keywords');
						$query = $this->EE->db->get_where('search', array('search_id' => $search_id));
        			
						if ($query->num_rows() > 0)
						{
							$this->which = 'ee';
							$this->search_terms[] = $query->row('keywords');
						}
        			}				
				break;
				case 'google'	:
					if (preg_match('/^http:\/\/(www)?\.?google.*/i',$ref))
					{
						$this->which = 'google';
						
						$query = preg_replace('/^.*q=([^&]+)&?.*$/i','$1',$ref);
						$query = preg_replace('/\'|"/','',$query);
						$this->search_terms = preg_split ("/[\s,\+\.]+/",$query);
					}
				break;
				case 'lycos'	:
					if (preg_match('/^http:\/\/search\.lycos.*/i',$ref))
					{
						$this->which = 'lycos';
						
						$query = preg_replace('/^.*query=([^&]+)&?.*$/i','$1', $ref);
						$query = preg_replace('/\'|"/', '', $query);
						$this->search_terms = preg_split ("/[\s,\+\.]+/", $query);
					}
				break;
				case 'yahoo'	:
					if (preg_match('/^http:\/\/search\.yahoo.*/i', $ref))
					{
						$this->which = 'yahoo';
						
						if (strpos($ref,'search?x=') !== false)
						{
							$query = preg_replace('/^.*(\?x=op&)(.*)&vst?.*$/i','$2',$ref);
							$search_qualifiers = explode('&',$query);
							
							foreach($search_qualifiers as $v)
							{
								if (substr($v,0,3) == 'vp=' || substr($v,0,3) == 'vo=' || substr($v,0,3) == 'va=')
								{
									$v = substr($v,3);
									$v = preg_replace('/\'|"/','',$v);
									$temp_items = explode('+',$v);
									$this->search_terms = array_merge($this->search_terms,$temp_items);
								}
							}
						}
						else
						{
							$query = preg_replace('/^.*p=([^&]+)&?.*$/i','$1', $ref);
							$query = preg_replace('/\'|"/', '', $query);
							$this->search_terms = preg_split ("/[\s,\+\.]+/", $query);
						}
					}
				break;
			}	
			
			if ($this->which != '')
			{
				break;
			}
		}
		
		if (count($this->search_terms) == '0')
		{
			$this->return_data = ($param == '1') ? '' : $str;
            return;
        }
        elseif ($param == '1')
        {
        	$search_words		= implode(', ',$this->search_terms);
        	$str				= str_replace('{engine}',($this->which == 'ee') ? 'EE' : ucfirst($this->which),$str);
        	$this->return_data	= str_replace('{search_words}',$search_words, $str);        	
        	return;
        }
        
        // -------------------------------------
        //  Search Hilite Cache Writing
        // -------------------------------------
        
        if ($this->write_cache == 'y')
        {        
        	$this->_update_cache();     	
        }
        
		// -------------------------------------
		// Hilite Text
		// -------------------------------------
		
		foreach($this->search_terms as $hilite_text)
		{
			if (trim($hilite_text) == '')
			{
				continue;
			}
			
			$hilite_text = trim($hilite_text);
			
			// Code below taken from Dean Allen's similar script


			// we can't risk replacing an html tag or attribute, so if we don't find it in the 
			// string we do a mass search/replace, otherwise we only match the last find outside
			// of html tags
			
			if ( ! preg_match('/<[^>]*'.preg_quote($hilite_text).'[^>]*>/i',$str))
			{
				if ($partial == 'y')
				{
					$str = preg_replace('/('.preg_quote($hilite_text).')/i','<span class="hilite">$1</span>',$str); 
				}
				else
				{
					$str = preg_replace('/(\b'.preg_quote($hilite_text).'\b)/i','<span class="hilite">$1</span>',$str); 
				}
			}
			else
			{

				if ($partial == 'y')
				{
					$str = preg_replace('|(?<=>)([^<])?('.preg_quote($hilite_text).')|i','$1<span class="hilite">$2</span>',$str);
				}
				else
				{
					$str = preg_replace('|(?<=>)([^<]+)?(\b'.preg_quote($hilite_text).'\b)|i','$1<span class="hilite">$2</span>',$str);
				}				
			}
		}
		
		$this->return_data = $str;
	}
	
	// --------------------------------------------------------------------


	/**
	 * Update Cache
	 * 
	 * @access	private
	 * @return	bool
	 */
    function _update_cache()
    {
    	
        $this->cache_path	= APPPATH.'cache/'.$this->cache_name.'/'.$this->which;   
        
        // --------------------------
        // Check Cache
        // --------------------------
        
        if ( ! is_dir(APPPATH.'cache/'.$this->cache_name))
        {
        	if ( ! @mkdir(APPPATH.'cache/'.$this->cache_name, 0777))
        	{
        		return false;
        	}
        }
        
        	
        if ( ! file_exists($this->cache_path))
        {
        	// Create and write cache file
        	if ($fp = @fopen($this->cache_path, FOPEN_WRITE_CREATE_DESTRUCTIVE))
        	{
        		$data = implode(', ',$this->search_terms)."\t\t".date("F j, Y, g:i a", time());
        		
        		flock($fp, LOCK_SH);
        		fwrite($fp, $data);
        		flock($fp, LOCK_UN);
        		fclose($fp);
        	}
        }
        else
        {
        	$fp = @fopen($this->cache_path, FOPEN_READ_WRITE);
        	
        	if ( ! is_resource($fp))
        	{
        		return false;
        	}
        	else
        	{
        		flock($fp, LOCK_SH);
        		$data = trim(@fread($fp, filesize($this->cache_path)));
        		flock($fp, LOCK_UN);
        		fclose($fp); 
        		
        		$items_array = explode("\r\n",$data);
        		
        		if (count($items_array) > $this->cache_length)
        		{
        			$items_array = array_slice($items_array, 1);
        		}
        		
        		$items_array[] = implode(', ',$this->search_terms)."\t\t".date("F j, Y, g:i a", time());
        		$new_data = implode("\r\n",$items_array);
        		
        		if ($fp = @fopen($this->cache_path, 'wb'))
        		{
        			flock($fp, LOCK_SH);
        			fwrite($fp, trim($new_data));
        			flock($fp, LOCK_UN);
        			fclose($fp);         		
        		}      		
        	}
        }
        
		return true;
        
    }
	
	// --------------------------------------------------------------------
	
	/**
	 * Plugin Usage
	 *
	 * @access	public
	 * @return	string	plugin usage text
	 */
	function usage()
	{
		ob_start(); 
		?>

		Using a CSS class, this plugin will highlight the terms searched
		for whenever someone arrives at a template using various search engines.

		The following five searches engines are currently supported:
		A9, Dogpile, ExpressionEngine, Google, Lycos, and Yahoo


		STEP ONE:
		Add this style to your CSS and modify the color as you see fit.

		     .hilite { background-color: #ff0; }

		STEP TWO:
		Wrap your fields and content with the plugin tags.

		     {exp:search_hilite}
		     <p>{summary}</p>

		     <p>{body}</p>
		     {/exp:search_hilite}

		STEP THREE (Optional):
		Create a welcome message for incoming search engine users:

		     {exp:search_hilite welcome='true'}
		     <p>Welcome {engine} user!</p>
     
		     We noticed that you have arrived here via {engine} and have 
		     highlighted your search terms: {search_words}.
		     {/exp:search_hilite}

		This message will only display if a Search Engine referrer is available 
		and search terms are found in the referrer URL.

		******************
		VERSION 1.1
		******************
		 - Added the {search_words} varible.
		 - Added ExpressionEngine's search support
		 - Caching of search words by search engine possible.  Disabled by default.  To enable, go into
		   the plugin file and set the class variable $write_cache to 'y'.  There will now be a file 
		   created for each search engine in a newly create /system/cache/search_hilite/ directory.  Inside 
		   each file will be the search words and the time when the page was loaded for those terms.  
		   The file for a specific search engine will not be created until a search comes in from that 
		   search engine.
   
		******************
		VERSION 1.2
		******************
		 - Fixed a bug when there is a quote used in the original search engine's search.

		******************
		VERSION 1.2.1
		******************
		 - Made plugin compatible with PHP 4.4 and above

		******************
		VERSION 1.2.2
		******************
		 - Fixed a bug to allow for the search term to be highlighted multiple times within xhtml under most circumstances.

		******************
		VERSION 1.3
		******************
		 - Updated plugin to be 2.0 compatible.

		******************
		VERSION 1.3.1
		******************
		- Fixed a PHP error.


		<?php
		$buffer = ob_get_contents();

		ob_end_clean(); 

		return $buffer;
	}

	// --------------------------------------------------------------------

}
// END CLASS

/* End of file pi.search_hilite.php */
/* Location: ./system/expressionengine/third_party/search_hilite/pi.search_hilite.php */