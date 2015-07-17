<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
Copyright (C) 2004 - 2015 EllisLab, Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
ELLISLAB, INC. BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

Except as contained in this notice, the name of EllisLab, Inc. shall not be
used in advertising or otherwise to promote the sale, use or other dealings
in this Software without prior written authorization from EllisLab, Inc.
*/


/**
 * Search Hilite Class
 *
 * @package			ExpressionEngine
 * @category		Plugin
 * @author			EllisLab
 * @copyright		Copyright (c) 2004 - 2015, EllisLab, Inc.
 * @link			https://github.com/EllisLab/Search-Hilite
 */
class Search_hilite
{
	public $return_data	= '';
	public $supported 		= array('a9','dogpile', 'ee', 'google','lycos', 'yahoo');
	public $search_terms	= array();
	public $which 			= '';
	public $cache_name		= 'search_hilite';
	public $cache_path		= ''; // Path to cache file.
	public $write_cache	= 'n';  // Set to 'y' to write cache file
	public $cache_length	= 200;

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	function __construct($str = '',$param = '0')
	{
		ee()->load->helper('text');

		if ($str == '')
		{
        	$str = ee()->TMPL->tagdata;
        }

        $param = ( ! ee()->TMPL->fetch_param('welcome') || ee()->TMPL->fetch_param('welcome') != 'true') ? '0' : '1';
        $partial = ( ! ee()->TMPL->fetch_param('partial') || ee()->TMPL->fetch_param('partial') == 'true') ? 'y' : 'n';

        $ref = ( ! isset($_SERVER['HTTP_REFERER'])) ? '' : entities_to_ascii(ee()->security->xss_clean($_SERVER['HTTP_REFERER']));

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
						if ( ! isset(ee()->uri->segments[count(ee()->uri->segments)]))
						{
							continue;
						}
						else
       					{
							ee()->uri->query_string = ee()->uri->segments[count(ee()->uri->segments)];
						}

						if (strlen(ee()->uri->query_string) == 32)
						{
							$search_id = ee()->uri->query_string;
						}
						else
						{
							// We want to highlight the term when we click through from an EE search result, so we need to look at the referral last segment, not the current URL segment - @electriclabs - Rob Hodges 09/02/2012
							// Explode the referral URL
							$search_id = explode('/', $ref);
							// Grab the search ID from the referral URL and continue
							$search_id = ($search_id[count($search_id)-2]);
						}

						ee()->db->select('keywords');
						$query = ee()->db->get_where('search', array('search_id' => $search_id));

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
}
