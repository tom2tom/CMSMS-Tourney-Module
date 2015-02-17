<?php
$lang['friendlyname']='Tournaments';
$lang['postinstall']='Tournament module has been installed. Remember to apply relevant permissions.';
$lang['postuninstall']='Tournament module has been uninstalled.';
$lang['confirm_uninstall']='You\'re sure you want to uninstall the Tournament module?';
$lang['uninstalled']='Module uninstalled.';
$lang['installed']='Module version %s installed.';
$lang['upgraded']='Module upgraded to version %s.';
$lang['upgradefail']='Module upgrade aborted, error when attempting to %s';

$lang['perm_admin']='Modify Tournament-module Settings';
$lang['perm_mod']='Modify Tournament Brackets';
$lang['perm_score']='Modify Tournament-Bracket Results';
$lang['perm_view']='View Tournament Brackets';

$lang['admindescription']='Administer, edit, configure tournaments';
$lang['lackpermission']='You are not authorised to do that.';

$lang['telladmin'] = 'Please advise your site administrator.';

$lang['err_ajax']='Server Communication Error';
$lang['err_chart']='Chart creation failed';
//$lang['err_data']='Data processing error';
$lang['err_data_type']='Data processing error: %s';
$lang['err_export']='A problem occurred during the export process'; //too vague!
$lang['err_list']='List creation failed';
$lang['err_match']='team/match data error';
$lang['err_missing']='Cannot find a requested tournament';
$lang['err_notice']='A problem occurred during the communication process';
$lang['err_styles']='Cannot find specified styling file';
$lang['err_system']='System error!';
$lang['err_tag']='Invalid page definition';
$lang['err_token']='failed to save token';
$lang['err_template']='Display-template error';
$lang['err_value']='Invalid parameter value'; 
$lang['noname']='name missing';

$lang['tab_items']='Tournaments';
$lang['tab_config']='Settings';

$lang['title_teamname']='Team name';
$lang['title_name']='Name';
$lang['title_tag']='Page Tag';
$lang['title_status']='Status';
$lang['status_notyet']='Not started';
//$lang['status_started']='In progress';
//in the following, %s will be replaced by 'match' or 'matches'
$lang['status_going']='%d %s complete, %d pending';
$lang['status_ended']='Finished';
$lang['status_complete']='Completed';
$lang['no_tourney'] = 'No tournament is recorded.';

$lang['no_groups']='Not Allowed';
$lang['all_groups']='All Groups';
$lang['help_login']='Any logged-in user in this group may record match results';

//$lang['administer']='Administer';
$lang['clone']='Clone';
$lang['copy']='Copy';
$lang['delete']='Delete';
$lang['export']='Export';
$lang['exportxml']='Export XML';
$lang['chart']='Chart';
$lang['submit']='Submit';
$lang['submit2']='Submit Result';
$lang['list']='List';
$lang['plain']='Plain';
$lang['plain_tip']='Prepare chart without match-labels';
$lang['plan']='Plan';
$lang['plan_tip']='Show all matches';
$lang['actual']='Actual';
$lang['actual_tip']='Show pending matches';

$lang['confirm_delete']='Are you sure you want to delete %s?';
$lang['confirm_deletethis']='Are you sure you want to delete this %s?';
//these are for inclusion in the above template to create delete-confirmation prompts
$lang['match_data']='all match data';
$lang['sel_players']='selected players';
$lang['sel_teams']='selected teams';

$lang['deleted2']='Deleted %s.';
//for replacement score-string
$lang['teamgone']='Withdrew';
$lang['edit']='Edit';
$lang['import']='Import';
$lang['upload']='Upload';
$lang['update']='Update';
//$lang['export_selected_records']='export selected records';
$lang['export_tip']='Export data for selected competitors to .csv file';
$lang['import_tip']='Import competitors data from selected .csv file';
$lang['delete_tip']='Delete selected competitors';
$lang['upload_tip']='Upload selected file to website host';
$lang['update_tip']='Save data for selected rows';
$lang['submit_tip']='Send result to tournament manager';
$lang['err_save']='Error during data save.';

$lang['team']='team';
$lang['player']='competitor';

$lang['titlescored']='Matches to be scored';
$lang['titlewhen']='Completed at';
$lang['titlesender']='Your name';
$lang['titlecomment']='Comment';
$lang['titlecaptcha']='The characters shown in the image above';
$lang['missing']='not provided';
$lang['reporter']='Submitted by %s';
$lang['chooseone']='Choose one';
$lang['sentok']='Messages successfully sent';
$lang['notsent']='Error during message transmission';

$lang['err_captcha']='Incorrect validation text';
$lang['err_nocaptcha']='No validation text';
$lang['err_file']='Unsuitable file content';
$lang['err_nomatch']='No match selected';
$lang['err_noresult']='No result selected';
$lang['err_noresult2']='No result provided';
$lang['err_nosender']='You must give your name';
$lang['err_notime']='You must report finish time, or date and time, for completed matches';

//$lang['won']='Won';
//$lang['forfeited']='Forfeited';
$lang['notyet']='Not yet';
$lang['none']='none';

//templates for displaying match results. %s('s) become(s) teamname(s), %r becomes a bracket-specific relation like 'defeated'
//any ' ' or ',' in the following will be replaced by '\n' for in-box wrapping in bracketcharts
$lang['versus_fmt']='%s %r %s';
$lang['won_fmt']='%s won';
$lang['or_fmt']='%s or %s';
$lang['defeated_fmt']='%s %r %s';
$lang['forfeited_fmt']='%s %r';
$lang['tied_fmt']='%s,%s %r';
$lang['abandoned_fmt']='%s,%s %r';
//for numbered-round-naming, again %r becomes 'Round' or whatever, %d becomes number
$lang['round_fmt']='%r %d';

//defaults for match results
//$lang['won']='won';
$lang['defeated']='defeated';
$lang['forfeited']='Forfeited';
$lang['abandoned']='Abandoned';
$lang['tied']='Tied';
$lang['bye']='Bye';

$lang['nomatch']='No match';
$lang['match']='match';
$lang['matches']='matches';
$lang['matchnum']='Match %d';
$lang['anonanon']='Match'; //informative NOT!
$lang['numother']='%s competitor';
$lang['anonother']='Another competitor';
$lang['numloser']='%s loser';
$lang['anonloser']='Prior match loser';
$lang['numwinner']='%s winner';
$lang['anonwinner']='Prior match winner';

$lang['add']='Add';
$lang['title_add_tourn']='Add new tournament';
$lang['added']='Added';
$lang['tournament']='Tournament';

$lang['yes']='Yes';
$lang['no']='No';
$lang['one']='One';
//$lang['any']='Any';
$lang['all']='All';

$lang['title_import']='Import tournament from XML file';
$lang['err_import_failed']='Tournament import failed.';
$lang['success_import']='Tournament imported successfully.';

$lang['title_teamimport']='Import team data for tournament \'%s\' from CSV file';
$lang['team_import_failed']='Team data import failed.';
$lang['team_imported']='Team data imported successfully.';
$lang['help_teamimport']=<<< EOS
<h3>File Format Information</h3>
<p>The input file must be in ASCII format with data fields separated by commas.
Any actual comma in a field should be represented by '&amp;#44;'.
Each line in the file (except the header line, discussed below) represents one team.</p>
<h4>Header Line</h4>
<p>The first line of the file names the fields in the file. The first field name must be '##Teamname' (without the quotes).
After that, up to two optional fields, named '#Seeded' and/or '#Tellall' (again, no quotes).
Further fieldnames, if they exist, can have any names but must be in pairs - the first of each
to hold a player name, the second to hold contact information for that player.
There may be any number of such pairs. For example:<br />
<code>##Teamname,Player,Contact</code> or<br />
<code>##Teamname,#Seeded,#Tellall,Captain,Contact1,Player2,Contact2</code></p>
<h4>Other Lines</h4>
<p>The data in each line must conform to the header columns, of course. Any field, or entire line, may be empty.
The tellall field will be treated as TRUE if it contains something other than '0' or 'no' or 'NO' (no quotes, untranslated).</p>
<h3>Problems</h3>
<p>The import process will fail if:<ul>
<li>the first one, two or three '#'-prefixed field names are are not as expected</li>
<li>the number of player-specific fields is an odd number</li>
<li>there are fewer fields in any line of data than there are fieldnames in the header line</li>
</ul></p>
<h3>You Decide What to Keep</h3>
<p>Imported data are <strong>not automatically stored</strong> in the database.
After review and any modification, you should save the tournament data in the normal manner.;
EOS;

//settings

$lang['select_one']='Select One';
$lang['title_bracket_single']='Knockout Tournament';
$lang['title_bracket_double']='Double Elimination Tournament';
$lang['title_bracket_round']='Round-Robin Tournament';

$lang['title_names_fieldset']='Default Names';
$lang['title_misc_fieldset']='Miscellaneous';

$lang['title_cssfile']='File containing style-parameters';
$lang['help_cssfile']='May be empty to use default styles. Otherwise, module help includes details of file content and location';
$lang['title_cssfile2']='File containing style-parameters for %s tournament';
$lang['help_cssupload']=
'<h3>File Format Information</h3>
<p>The file must be in ASCII stylesheet format. Module help includes details of file content.</p>
<h3>Problems</h3>
<p>The upload process will fail if:<ul>
<li>the file does not look like a relevant stylesheet</li>
<li>the file-size is bigger than about 2 kB</li>
<li>filesystem permissions are insufficient</li>
</ul></p>
';

$lang['showhelp']='click to toggle display of information about this parameter';

$lang['title_zone']='Time zone';
$lang['help_zone']='Default setting for local/tournament time';
$lang['help_zone2']='For local/tournament time';
$lang['title_date_format']='Date format';
$lang['help_date_format']='A string including format characters recognised by PHP\'s date() function. For reference, please check the <a href="http://php.net/manual/en/function.date.php">php manual</a>. Remember to escape any characters you don\'t want interpreted as date format codes!';
$lang['title_time_format']='Time format';
$lang['help_time_format']='See information above about date format';
$lang['title_latitude'] = 'Latitude';
$lang['help_latitude'] = 'Needed only for sunrise/sunset calculations for available times. Accuracy up to 3 decimal places.';
$lang['title_longitude'] = 'Longitude';
$lang['help_longitude'] = 'See advice for latitude.';
$lang['title_uploads_dir']='Sub-directory for module-specific file uploads';
$lang['help_uploads_dir']='Filesystem path relative to website-host uploads directory. No leading or trailing path-separator, and any intermediate path-separator must be host-system-specific e.g. \'\\\' on Windows. If left blank, the default will be used. Directory could contain .css and/or .ttf files for charts, among others.';
//$lang['title_css_class']='CSS Class';
//$lang['help_css_class'] = 'Optional name of class, or space-separated series of class names, applied to list views';
$lang['title_strip_on_export']='Strip HTML tags on export';
$lang['help_strip_on_export']='Remove all HTML tags from records when exported to .csv';
$lang['title_export_file']='Export to host';
$lang['help_export_file']='This option progressively creates a .csv file in the site\'s <i>uploads</i> directory rather than processing the export in memory. This is good if there is a lot of data to export. The downside is that someone needs to get that file and (usually) then delete it.';
$lang['title_export_file_encoding']='Character-encoding of exported content';

$lang['title_final']='Title of final match in the tournament';
$lang['title_semi']='Title of match in second-last round';
$lang['title_quarter']='Title of match in third-last round';
$lang['title_eighth']='Title of match in fourth-last round';
$lang['title_roundname']='Title of other matches (will have number appended)';
$lang['help_match_names']='If the title of any of final...fourth-last matches is left blank, this name will be used there, too';
$lang['name_last_match']='Final';
$lang['name_2ndlast_match']='Semi Final';
$lang['name_3rdlast_match']='Quarter Final';
$lang['name_4thlast_match']='Round of 16';
$lang['name_other_match']='Round';

$lang['title_against']='Text for separating opponents';
$lang['title_defeated']='Text for defeated opponent';
$lang['title_cantie']='Matches may be tied';
$lang['noties']='Matches may <strong>not</strong> be tied';
$lang['yesties']='Matches may be tied'; //c.f. above
$lang['title_tied']='Text for tied match';
$lang['title_noop']='Name of non-existent opponent';
$lang['title_forfeit']='Result for match won because opponent could not start or finish';
$lang['title_abandoned']='Result for abandoned match (which has no winner)';
$lang['name_against']='vs';
$lang['name_defeated']='defeated';
$lang['name_tied']='tied with';
$lang['name_no_opponent']='Bye';
$lang['name_forfeit']='Forfeit';
$lang['name_abandoned']='Abandoned';

//$lang['tab_user_options']='Frontend Options';
//$lang['tab_admin_options']='Admin Options';
//$lang['tab_user_comp']='Frontend View';
//$lang['tab_admin_comp']='Admin View';

$lang['sunrise']='sunrise';
$lang['sunset']='sunset';
$lang['week']='week'; //so we can skip parsing ['periods'] sometimes
//time interval names - singular & plural
//names are expected to be all lower-case, will be capitalised on demand,
//must be ordered shortest..longest, comma-separated, no whitespace
$lang['periods']='minute,hour,day,week,month,year';
$lang['multiperiods']='minutes,hours,days,weeks,months,years';

//popup calendar titles
//$lang['title_month']='Month';
$lang['nextm']='Next Month';
$lang['prevm']='Previous Month';
//$lang['title_year']='Year';
//longform daynames - must be Sunday first, comma-separated, no whitespace
$lang['longdays']='Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday';
//shortform daynames - must be Sunday first, comma-separated, no whitespace 
$lang['shortdays']='Sun,Mon,Tue,Wed,Thu,Fri,Sat';
//longform monthnames - must be January first, comma-separated, no whitespace
$lang['longmonths']='January,February,March,April,May,June,July,August,September,October,November,December';
//shortform monthnames - must be January first, comma-separated, no whitespace
$lang['shortmonths']='Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec';

//specific tournament

$lang['tab_main']='Tournament';
$lang['tab_advanced']='Advanced';
$lang['tab_schedule']='Schedule';
$lang['tab_chart']='Chart';
$lang['tab_players']='Competitors';
$lang['tab_matches']='Matches';
$lang['tab_results']='Results';

$lang['title_title']='Displayed title';
$lang['title_alias']='Alias name';
$lang['help_alias']='For use in web-page smarty tags that display this tournament. If left blank, a default will be applied.';
$lang['title_desc']='Description';
$lang['title_type']='Type';
$lang['title_teamsize']='Players per team';

$lang['title_owner']='Responsible person';
$lang['title_contact']='Contact details for responsible person';
$lang['title_twtfrom']='Tweets to participants sent from';
//$lang['title_admin_eds']='Admin users group whose members can edit tournament results';
$lang['title_feu_eds']='FEU group whose members can edit tournament results';
$lang['help_owner']='The person responsible for the integrity of tournament data';
$lang['help_editors']='Leave blank for no restriction';
$lang['help_contact']='Phone or email or handle of responsible person';
$lang['help_twt1']=<<<EOS
Twitter account handle. If left blank, @CMSMSTourney will be used.
Anything else will require authorisation by the account owner, before first use.
Module help has more details.
EOS;
$lang['help_twt2']='You can refresh the current authorisation for <strong>%s</strong>, or initiate a change, by clicking the button below.';
$lang['help_twt3']='You can initiate authorisation by clicking the button below.';
$lang['help_twt4']='<strong>Ensure all wanted data are saved</strong>, before departing.';

$lang['title_chttemplate']='Front-end chart display template';
$lang['title_emailhtml']='Generate HTML email';
$lang['title_emailcoding']='Email character-encoding';
$lang['title_mailouttemplate']='Match-announcement email template';
$lang['title_tweetouttemplate']='Match-announcement tweet template';
$lang['title_mailintemplate']='Match-result email template';
$lang['title_tweetintemplate']='Match-result tweet template';
$lang['title_logic']='Result validation';

$lang['help_template']='The following smarty variables are available for use in the template.';
$lang['help_chttemplate']='At a minimmum, include {$image}';
$lang['seeabove']='See corresponding information about email, above';

$lang['desc_title']='the title as specified on the Tournament tab';
$lang['desc_description']='the description as specified on the Tournament tab';
$lang['desc_owner']='the responsible person as specified on the Tournament tab';
$lang['desc_contact']='contact information for the responsible person as specified on the Tournament tab';
$lang['desc_image']='an XHTML string specifying the image file to be displayed';
$lang['desc_imgdate']='formatted date/time when the image file was created or last changed';
$lang['desc_imgheight']='height of the displayed image';
$lang['desc_imgwidth']='width of the displayed image';

$lang['default_email']='Your next match: %s starting at %s';
$lang['desc_where']='the venue for the match';
$lang['desc_when']='the scheduled date and start-time for the match';
$lang['desc_date']='the date component of $when';
$lang['desc_time']='the time component of $when';
$lang['desc_opponent']='name of opponent';
$lang['desc_teams']='names of participants';
$lang['desc_recipient']='the person to receive the message';
$lang['desc_toall']='whether the message will be sent to all team members individually';
$lang['desc_report']='the reported information';

$lang['help_use_smarty'] = 'Plugins and smarty variables are valid in this field.';
$lang['help_mailout_template']='If left blank, the default template will be used.';
$lang['help_mailin_template']='At a minimum, include {$report}.<br />Or if left blank, the default template will be used.';
$lang['help_tweetout_template']='If left blank, the default template will be used.';
$lang['help_tweetin_template']='At a minimum, include {$report}.<br />Or if left blank, the default template will be used.';
$lang['help_logic']='Javascript/jquery to be executed before submitting a match result.<br />
This code is embedded in a js function, and must return false if validation fails';

$lang['title_start_date']='Preferred start date';
$lang['title_end_date']='Preferred finish date';
$lang['help_date']='Enter date in a format that php\'s strtotime() will recognise. See <a href="http://www.php.net/manual/en/datetime.formats.date.php">the php documentation</a>.<br />Note assumption about separators in abbreviated dates: m/d/y or d-m-y or d.m.y';
$lang['title_play_gap']='Minimum period between matches';
$lang['help_play_gap']='Recovery time for competitors. The number need not be whole.';

$lang['title_locale']='Locale identifier for localising displayed date/time';
$lang['help_locale']='
A "locale" is an identifier which can be used to get language-specific terms. Examples are "en_US" and "cs_CZ.UTF-8" and "zh_Hant_TW".
Refer to <a href="https://www.gnu.org/software/gettext/manual/html_node/Locale-Names.html">this reference</a> for more details.
Blank means use default.';
$lang['title_same_time']='Maximum number of contemporary matches';
$lang['title_hours']='Hour(s)';
$lang['title_days']='Day(s)';
$lang['title_weeks']='Week(s)';
$lang['title_calendar']='Calendar identifier';
$lang['help_calendar']='Schedule matches in accordance with reservations under this name, and if necessary, revert to days/times specified below.';
$lang['title_available']='Match scheduling conditions';
$lang['help_available'] = <<< EOS
One or more (in which case, comma-separated) conditions like 'P@T', where<br />
&#8226; (optional) 'P' represents a period-descriptor<br />
&#8226; (optional) 'T' represents a time-descriptor for period P<br />
&#8226; separator '@' is needed only if both P and T are present<br />
If T is not present for a P, all times are available for the days which match P.<br />
If P is not present for a T, the times apply to all days.<br />
Any P or T can be<br />
&#8226; a single value<br />
&#8226; a bracket-enclosed and comma-separated sequence of values (in any order)<br />
&#8226; a '..' separated range of sequential values<br />
For dates, month and day, or just day, are optional. Times are 24-hour, minutes are optional, 
the minute-separator must be ':'. In some contexts, expressing a time like H:00 may
be needed to discriminate between hours-of-day and days-of-month. Non-ranged times
each represent one hour. Other numeric values may be < 0, meaning count backwards.<br />
Examples:<br />
&#8226; 2000 or 2000..2005 or 2000-6 or 2000-10..2001-3 or 2000-9-1 or 2000-10-1..2000-12-31<br />
&#8226; January or November..December<br />
&#8226; for week(s)-of-any-month (some of which may not be 7-days): 2(week) or -1(week) or 2..3(week)<br />
&#8226; for week(s)-of-named-month: 2(week(March)) or or 1..3(week(July,August)) or (-2,-1)(week(April..July))<br />
&#8226; for day(s)-of-month: 1 or -2 or or 1..10 or 2..-1 or -3..-1<br />
&#8226; for day(s)-of-month: 1(Sunday) or -1(Wednesday..Friday) or 1..3(Friday,Saturday)<br />
&#8226; for days(s)-of-named-month: 2(March) or or 1..3(July,August) or (-2,-1)(April..July) or 2(Sunday(July..September))<br />
&#8226; for day(s)-of-week: Monday or Wednesday..Friday<br />
&#8226; for times: 9 or 12..23 or 6:30..15:30 or sunrise..16 or 9..sunset-3:30<br />
{$lang['help_use_smarty']}
EOS;

$lang['help_same_time']='Blank means no limit';
$lang['help_selection']='No selection means no restriction';

$lang['title_place_gap']='Expected match duration';
$lang['help_place_gap']='Minimum interval between matches at the same venue. The number need not be whole.';

$lang['title_seedtype']='Arrangement of seeded competitors';
$lang['seed_none']='Random';
$lang['seed_toponly']='Random except 1,2';
$lang['seed_balanced']='More-equal matches eg 1v3';
$lang['seed_unbalanced']='Less-equal matches eg 1v4';
$lang['help_seedtype']='Determines how seeds are allocated among initial matches';

$lang['title_order']='Order';
$lang['title_player']='Player';
$lang['title_team']='Team';
$lang['title_teams']='Teams';
$lang['title_seed']='Seeded';
$lang['title_contact']='Contact details';
$lang['title_move']='Re-order';
$lang['info_noteam']='No competitor is recorded.';
$lang['title_add']='Add new %s';
$lang['title_add_long']='ADD NEW %s TO %s';
$lang['title_edit_long']='UPDATE %s IN %s';

$lang['up']='Move up';
$lang['down']='Move down';

$lang['schedule']='Schedule';
$lang['reset']='Reset';
$lang['reset_tip']='Clear and renew all match data';
$lang['history']='History';
$lang['history_tip']='Show completed matches';
$lang['future']='Pending';
$lang['future_tip']='Show matches not yet completed';

$lang['notify']='Notify';
$lang['notify_tip']='Send match-time message to teams for selected rows';

$lang['changes']='Changelog';
$lang['changes_tip']='Show log of changes to match results';
$lang['title_changelog']='Logged result-changes for \'%s\' tournament';
$lang['nochanges']='There is no recorded change.';
$lang['title_changer']='Changer';
$lang['title_changewhen']='When';
$lang['title_olddata']='Before Change';
$lang['title_newdata']='After Change';

$lang['scheduled']='Scheduled';
$lang['played']='Played';
$lang['score']='Score';

$lang['help_dnd']='You can change the order by dragging any row, or double-click on any number of rows prior to dragging them all.';

$lang['info_nomatch']='No match is recorded.';
$lang['info_nomatch2']='All matches are complete.';
$lang['info_noresult']='No result is recorded.';
$lang['info_noresult2']='All results are complete.';

$lang['title_mid']='Match';
$lang['title_venue']='Venue';
$lang['possible']='Maybe';
$lang['confirmed']='Confirmed';
$lang['title_result']='Result';

//===========================

$lang['help_teamname']='If blank, the name of the first-listed player will be used';
$lang['title_ordernum']='Order number';
$lang['help_order']='Competitors are ordered by this number, on the tournament\'s competitors tab.<br />
You may enter -1 to place this competitor first, leave blank to place it last.<br />
The displayed order has no effect on opponent selection, but may effect scheduling.';
$lang['title_sendto']='Give match-related notices to';
$lang['help_sendto']='If \'one\', they should go to the first-listed player having usable contact-details';
$lang['deleteplayer']='Remove this player from the team';

//===========================
//in the following, text between >> and << is used for a link
$lang['chart_noshow']='This browser can\'t display the tournament chart. You can get the chart from >>here<<, and review it using some other application.';

$lang['comp_template_name'] = 'from %s';
$lang['title_tnmt_oldname']='Cloned Tournament';
//$lang['alias']='Alias';
//$lang['type']='Type';

$lang['save']='Submit';
$lang['saved']='Saved';

//any ' in these prompts must be double-escaped for js inclusion
$lang['allsaved']='Are all matches\\\' data saved ?';
$lang['abandon']='Abandon changes ?';

$lang['title_auth']='Authorise tournament-related tweets from a specific twitter account';
$lang['help_auth1']='Tournament: %s';
$lang['help_auth2']='Nominated account: %s';
$lang['connect']='Visit Twitter';

$lang['comp_deleted']='Tournament deleted.';

//$lang['update']='Update Tournament';
$lang['updated'] = 'Updated.';
$lang['updated2'] = 'Updated %s.';
$lang['view']='View';
$lang['cancel']='Cancel';
$lang['close']='Close';
$lang['prefs_updated']='Settings updated.';
//$lang['browser']='Browser %s';
$lang['apply']='Apply';
$lang['apply_tip']='Save and continue editing';

$lang['params_tmt_alias']='Tournament alias.';
$lang['params_view_type']='Tournament display type: \'chart\' or \'list\'.';
$lang['params_chart_css']='Name of uploaded .css file with chart-styling details.';
$lang['params_tweet_auth']='Show only a twitter-account authorisation button.';

/*
$lang['event_info_ResultAdd']='Event triggered after a match record is first populated';
$lang['event_help_ResultAdd']='<p>Event triggered after a match record is first populated</p>
<h4>Parameters</h4>
<ul>
<li><em>alias</em> - The tournament alias (string)</li>
<li><em>record_id</em> - The internal response id (int)</li>
<li><em>side</em> - Where the edit was initiated ("admin" or "user")</li>
</ul>';

$lang['event_info_MatchChange']='Event triggered before a match record is saved';
$lang['event_help_OnTourneyMatchChange']='<p>Event triggered before a match record is saved</p>
<h4>Parameters</h4>
<ul>
<li><em>alias</em> - The tournament alias (string)</li>
<li><em>side</em> - Where the addition was initiated ("admin" or "user")</li>
</ul>';
*/

//with parameter replacement, any literal '%' char must be doubled
$lang['help']=<<<EOS
<h3>What Does This Do?</h3>
<p>This module allows you to manage competitions of several sorts, by scheduling matches, recording results, and enabling participants and others to keep abreast of progress.</p>
<h3>How Do I Use It?</h3>
<p>In the CMSMS admin Content Menu, you should see a menu item called 'Tournaments'.
Click on that. On the displayed page, there are (to the extent that you're suitably authorised)
links and inputs by which you can add or change a tournament, or change module settings.</p>
<h3>Adding a Tournament to a Page</h3>
<p>Suitably authorised users will see, on the module's admin page, the tag used to display each tournament.
Each tag looks something like <pre>{cms_module module='Tourney' alias='sample_comp'}</pre></p>
<p>Put such a tag into the content of a page or into a template, optionally with extra parameters as described below.
<h3>Tournament Charts</h3>
<p>These are PDF files, created using a UTF-8-capable <a href="http://www.fpdf.org/en/script/script92.php">variant</a> of the <a href="http://www.fpdf.org">FPDF</a> library.
The PHP extension 'mbstring' must be available if UTF-8-encoded text is to be displayed.</p>
<h3>Chart Styling</h3>
<p>Styling is managed using css-like information. Recognised classes are<ul>
<li>.chart</li>
<li>.box, and related pseudo-classes ':nonfirm', ':firm', ':played' and ':winner'</li>
<li>.line</li>
</ul></p>
<p>Styling is applied at the server end, using a very-basic mechanism.
Box-side-specific styling is not supported, apart from horizontal and vertical margins.
Chart can have a pseudo-property 'gapwidth', representing extra horizontal distance between boxes' margins.
Background images and rare properties like 'letter-spacing' are not supported.
Relative values like 'small', '%%' or 'em' have no meaning.</p>
<p>For example, the following represents the default settings:
<pre>%s</pre><br />
You can specify the name of a stylesheet file as one of the parameters for the tournament, either in the tournament settings or the relevant page tag. The file is expected to be located in the website's uploads directory or, depending on the relevant module preference, a descendant of that directory.
The file does not need to be unique to a specific tournament. Absent such file, the defaults are applied.</p>
<p>The module ships with various Type1 fonts, which can be seen in the .../lib/font folder. Extra fonts can be <a href="http://www.fpdf.org/en/tutorial/tuto7.htm">manually installed</a>.<br />
Truetype fonts can be used, and would typically result in smaller-sized chart files, as only the used-characters are embedded in the file. The module ships with serif, sans, condensed and mono font-families (thanks to URW). Other .ttf files may be installed as-is in the uploads sub-directory specified in the module settings, or in the module sub-directory ".../lib/font/ttf". Several 'standard' truetype fonts (courier etc) are simulated. These can be seen in ".../lib/font/unifont".</p>
<h3>Communication by Tweet</h3>
<p>A twitter application 'CMSMS TourneyModule' is used to channel tweets to participants and/or the responsible person, where those persons' contact is a twitter handle.</p>
<p>One twitter account (@CMSMSTourney) has authorised that app, and any other account may likewise do so. To send from a different account, either<ul>
<li>a module user needs to authorise i.e. supply the account password, or</li>
<li>the website needs a mechanism which enables the account holder to independently authorise</li>
</ul>
before the account is used.</p>
<p>For the latter, you can create, and at least temporarily enable, a page with tag like<pre>{cms_module module='Tourney' alias='sample_comp' tweetauth=1}</pre>or just<br />{cms_module module='Tourney' tweetauth=1}</pre><br /><br />and refer the account holder there.</p>
<h3>Support</h3>
<p>This module is provided as-is. Please read the text of the license for the full disclaimer.</p>
<p>For help:<ul>
<li>discussion may be found in the <a href="http://forum.cmsmadesimple.org">CMS Made Simple Forums</a>; or</li>
<li>you may have some success emailing the author directly.</li>
</ul></p>
<p>For the latest version of the module, or to report a bug, visit the module's <a href="http://dev.cmsmadesimple.org/projects/tourney">forge-page</a>.</p>
<h3>Copyright and License</h3>
<p>Copyright &copy; 2014-2015 Tom Phane. All rights reserved.</p>
<p>This module has been released under version 3 of the <a href="http://www.gnu.org/licenses/agpl.html">GNU Affero General Public License</a>, and may be used only in accordance with the terms of that licence, or any later version of that license which is applied to the module.<br />
The included fonts have been licensed by <a href="http://www.urwpp.de/english/home.html">URW</a> under the GPL.</p>
EOS;
?>
