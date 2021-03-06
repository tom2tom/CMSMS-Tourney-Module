<?php
$lang['admindescription']='Administer, edit, configure tournaments';
$lang['friendlyname']='Tournaments';
$lang['postinstall']='Tournament module has been installed. Remember to apply relevant permissions.';
$lang['postuninstall']='Tournament module has been uninstalled.';
$lang['confirm_uninstall']='You\'re sure you want to uninstall the Tournament module?';
$lang['uninstalled']='Module uninstalled.';
$lang['installed']='Module version %s installed.';
$lang['upgraded']='Module upgraded to version %s.';

//templates for displaying match results. %s('s) become(s) teamname(s), %r becomes a bracket-specific relation like 'defeated'
//any ' ' or ',' in the following will be replaced by '\n' for in-box wrapping in bracketcharts
$lang['abandoned_fmt']='%s,%s %r';
$lang['defeated_fmt']='%s %r %s';
$lang['forfeited_fmt']='%s %r';
$lang['or_fmt']='%s or %s';
$lang['tied_fmt']='%s,%s %r';
$lang['versus_fmt']='%s %r %s';
$lang['won_fmt']='%s won';
//for numbered-round-naming, again %r becomes 'Round' or whatever, %d becomes number
$lang['round_fmt']='%r %d';

//time interval names - singular & plural
//names are expected to be all lower-case, will be capitalised on demand,
//must be ordered shortest..longest, comma-separated, no whitespace
$lang['periods']='minute,hour,day,week,month,year';
$lang['multiperiods']='minutes,hours,days,weeks,months,years';

$lang['abandon']='Abandon';
$lang['abandon_tip']='send cancelled-match message to teams for selected rows';
$lang['abandoned']='Abandoned';
$lang['activate']='Activate';
$lang['activeselgrp']='toggle activation of selected groups';
$lang['actual']='Actual';
$lang['actual_tip']='show pending matches';
$lang['add']='Add';
$lang['added']='Added';
$lang['addgroup']='Add new group';
$lang['all']='All';
$lang['all_groups']='All Groups';
$lang['allabandon']='Abandon changes ?';
$lang['allsaved']='Is everything saved that needs to be ?';
$lang['anonanon']='Match'; //informative NOT!
$lang['anonloser']='Prior match loser';
$lang['anonother']='Another competitor';
$lang['anonwinner']='Prior match winner';
$lang['apply']='Apply';
$lang['apply_tip']='save and continue editing';
$lang['asked']='Requested';

$lang['bye']='Bye';

$lang['cancel']='Cancel';
$lang['cancelled']='Cancelled';
$lang['cancelled_email']='Your match that was to start at %s has been cancelled';
$lang['changes']='Changelog';
$lang['changes_tip']='show log of changes to match results';
$lang['chart']='Chart';
//in the following, text between >> and << is converted into a link, do NOT omit those brackets
$lang['chart_noshow']='This browser can\'t display the tournament chart. You can get the chart from >>here<<, and review it using some other application.';
$lang['chooseone']='Choose one';
$lang['clone']='Clone';
$lang['clonesel_tip']='clone selected tournaments';
$lang['close']='Close';
$lang['comp_deleted']='Tournament deleted.';
$lang['comp_template_name']='from %s';
$lang['confirm']='Are you sure?';
$lang['confirm_delete']='Are you sure you want to delete %s?';
$lang['confirm_deletethis']='Are you sure you want to delete this %s?';
$lang['confirmed']='Confirmed';
$lang['connect']='Visit Twitter';
$lang['copy']='Copy';

$lang['default_email']='Your next match: %s starting at %s';
$lang['defeated']='defeated';
$lang['delete']='Delete';
$lang['delete_tip']='delete selected competitors';
$lang['deleted2']='Deleted %s.';
$lang['deleteplayer']='Remove this player from the team';
$lang['deletesel_tip']='delete selected tournaments';
$lang['deleteselgrp']='delete selected groups';
$lang['desc_contact']='contact information for the responsible person as specified on the Tournament tab';
$lang['desc_date']='the date component of $when';
$lang['desc_description']='the description as specified on the Tournament tab';
$lang['desc_image']='an XHTML string specifying the image file to be displayed';
$lang['desc_imgdate']='formatted date/time when the image file was created or last changed';
$lang['desc_imgheight']='height of the displayed image';
$lang['desc_imgwidth']='width of the displayed image';
$lang['desc_opponent']='name of opponent (blank/empty if unknown)';
$lang['desc_owner']='the responsible person as specified on the Tournament tab';
$lang['desc_recipient']='the person to receive the message';
$lang['desc_report']='the reported information';
$lang['desc_smsfrom']='phone number identified as source of SMS messages, if allowed by gateway';
$lang['desc_teams']='names of participants';
$lang['desc_time']='the time component of $when';
$lang['desc_title']='the title as specified on the Tournament tab';
$lang['desc_toall']='whether the message will be sent to all team members individually';
$lang['desc_when']='the scheduled date and start-time for the match';
$lang['desc_where']='the venue for the match';
$lang['down']='Move down';

$lang['edit']='Edit';
$lang['err_ajax']='Server Communication Error';
$lang['err_captcha']='Incorrect validation text';
$lang['err_chart']='Chart creation failed';
$lang['err_data_type']='Data processing error: %s';
$lang['err_export']='A problem occurred during the export process'; //too vague!
$lang['err_file']='Unsuitable file content';
$lang['err_import_failed']='Tournament import failed.';
$lang['err_list']='List creation failed';
$lang['err_match']='team/match data error';
$lang['err_missing']='Cannot find a requested tournament';
$lang['err_nocaptcha']='No validation text';
$lang['err_nomatch']='No match selected';
$lang['err_noresult']='No result selected';
$lang['err_noresult2']='No result provided';
$lang['err_nosender']='You must give your name';
$lang['err_notice']='A problem occurred during the communication process';
$lang['err_notime']='You must report finish time, or date and time, for completed matches';
$lang['err_save']='Error during data save.';
$lang['err_styles']='Cannot find specified styling file';
$lang['err_system']='System error!';
$lang['err_tag']='Invalid page definition';
$lang['err_template']='Display-template error';
$lang['err_text']='Invalid SMS content';
$lang['err_token']='failed to save token';
$lang['err_upgrade']='Module upgrade aborted, error when attempting to %s';
$lang['err_value']='Invalid parameter value';
$lang['export']='Export';
$lang['export_tip']='export data for selected competitors to .csv file';
$lang['exportsel_tip']='export selected tournaments';
$lang['exportxml']='Export XML';

$lang['fix_adjacent']='Most-equal matches e.g.-1v-2';
$lang['fix_balanced']='More-equal matches eg -1v-3';
$lang['fix_none']='None';
$lang['fix_unbalanced']='Less-equal matches eg -1v-4';
$lang['forfeited']='Forfeited';
$lang['future']='Pending';
$lang['future_tip']='show matches not yet completed';

$lang['getscore']='Request';
$lang['getscore_tip']='send score-report request to teams for selected rows';
$lang['groupdefault']='Ungrouped';
$lang['groupsel_tip']='put selected tournaments into a group';

$lang['help_alias']='For use in web-page smarty tags that display this tournament. If left blank, a default will be applied.';
$lang['help_auth1']='Tournament: %s';
$lang['help_auth2']='Nominated account: %s';
$lang['help_calendar']='Schedule matches in accordance with reservations under this name, and if necessary, revert to days/times specified below.';
$lang['help_chttemplate']='At a minimmum, include {$image}';
$lang['help_contact']='Phone or email or handle of responsible person';
$lang['help_cssfile']='May be empty to use default styles. Otherwise, module help includes details of file content and location';
$lang['help_date']='Enter date in a format that php\'s strtotime() will recognise. See <a href="http://www.php.net/manual/en/datetime.formats.date.php">the php documentation</a>.<br />Note assumption about separators in abbreviated dates: m/d/y or d-m-y or d.m.y';
$lang['help_date_format']='A string including format characters recognised by PHP\'s date() function. For reference, please check the <a href="http://php.net/manual/en/function.date.php">php manual</a>. Remember to escape any characters you don\'t want interpreted as date format codes!';
$lang['help_dnd']='You can change the order by dragging any row, or double-click on any number of rows prior to dragging them all.';
$lang['help_editors']='Leave blank for no restriction';
$lang['help_export_file']='This option progressively creates a .csv file in the site\'s <i>uploads</i> directory rather than processing the export in memory. This is good if there is a lot of data to export. The downside is that someone needs to get that file and (usually) then delete it.';
$lang['help_fixtype']='Determines how players seeded < 0 are assigned to first-round matches';
$lang['help_group']='Tournaments can be grouped to facilitate allocation of players and places across multiple events';
$lang['help_latitude']='Needed only for sunrise/sunset calculations for available times. Accuracy up to 3 decimal places.';
$lang['help_login']='Any logged-in user in this group may record match results';
$lang['help_longitude']='See advice for latitude.';
$lang['help_mailin_template']='At a minimum, include {$report}.<br />Or if left blank, the default template will be used.';
$lang['help_mailout_template']='If left blank, the default template will be used.';
$lang['help_match_names']='If the title of any of final...fourth-last matches is left blank, this name will be used there, too';
$lang['help_owner']='The person responsible for the integrity of tournament data';
$lang['help_phone_regex']='Regular-expression - see <a href="http://www.regexlib.net/Search.aspx?k=phone">this documentation</a>, for example';
$lang['help_place_gap']='Minimum interval between matches at the same venue. The number need not be whole.';
$lang['help_play_gap']='Recovery time for competitors. The number need not be whole.';
$lang['help_same_time']='Blank means no limit';
$lang['help_seedtype']='Determines how seeds are allocated among initial matches';
$lang['help_selection']='No selection means no restriction';
$lang['help_sendto']='If \'one\', they should go to the first-listed player having usable contact-details';
$lang['help_smsfrom']='This will be displayed if the SMS-provider allows. Include relevant country-prefix.';
$lang['help_smsprefix']='One to four numbers e.g. 1 for USA. <a href="http://countrycode.org">Search</a>. Include a leading \'+\' if the SMS gateway requires that.';
$lang['help_strip_on_export']='Remove all HTML tags from records when exported to .csv';
$lang['help_teamname']='If blank, the name of the first-listed player will be used';
$lang['help_template']='The following smarty variables are available for use in the template.';
$lang['help_time_format']='See information above about date format';
$lang['help_tweetin_template']='At a minimum, include {$report}.<br />Or if left blank, the default template will be used.';
$lang['help_tweetout_template']='If left blank, the default template will be used.';
$lang['help_twt1']='Twitter account handle. The default is @CMSMSNotifier, others may be authorised by the respective account owners.';
$lang['help_twt5']='You can initiate or refresh authorisation by clicking the \'Visit\' button. <strong>Ensure all wanted data are saved</strong>, before departing.';
$lang['help_uploads_dir']='Filesystem path relative to website-host uploads directory. No leading or trailing path-separator, and any intermediate path-separator must be host-system-specific e.g. \'\\\' on Windows. If left blank, the default will be used. Directory could contain .css and/or .ttf files for charts, among others.';
$lang['help_use_smarty']='Plugins and smarty variables are valid in this field.';
$lang['help_zone']='Default setting for local/tournament time';
$lang['help_zone2']='For local/tournament time';
$lang['history']='History';
$lang['history_tip']='show completed matches';

$lang['import']='Import';
$lang['imports_done']='%d result(s) imported';
$lang['imports_skipped']='%d result(s) (%s) ignored';
$lang['importresult_tip']='import results data from selected .csv file';
$lang['importteam_tip']='import competitors data from selected .csv file';
$lang['inactive']='inactive';
$lang['info_nomatch']='No match is recorded.';
$lang['info_nomatch2']='All matches are complete.';
$lang['info_noresult']='No result is recorded.';
$lang['info_noresult2']='All results are complete.';
$lang['info_noteam']='No competitor is recorded.';

$lang['lackpermission']='You are not authorised to do that.';
$lang['list']='List';
//longform daynames - must be Sunday first, comma-separated, no whitespace
$lang['longdays']='Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday';
//longform monthnames - must be January first, comma-separated, no whitespace
$lang['longmonths']='January,February,March,April,May,June,July,August,September,October,November,December';

$lang['match']='match';
$lang['match_added']='Match %d result added';
$lang['match_data']='all match data'; //inclusion in typed delete-confirm string
$lang['matches']='matches';
$lang['matchnum']='Match %d';
$lang['meridiem'] = 'AM,PM'; //upper-case, comma-separated, ante-first
$lang['missing']='not provided';

$lang['name_2ndlast_match']='Semi Final';
$lang['name_3rdlast_match']='Quarter Final';
$lang['name_4thlast_match']='Round of 16';
$lang['name_abandoned']='Abandoned';
$lang['name_against']='vs';
$lang['name_defeated']='defeated';
$lang['name_forfeit']='Forfeit';
$lang['name_last_match']='Final';
$lang['name_no_opponent']='Bye';
$lang['name_other_match']='Round';
$lang['name_tied']='tied with';
$lang['nextm']='Next Month';
$lang['no']='No';
$lang['no_feuedit']='Not Allowed';
$lang['no_groups']='No group is recorded.';
$lang['no_tourney']='No tournament is recorded.';
$lang['nochanges']='There is no recorded change.';
$lang['nochannel']='No message can be sent, no communication channel is available. Please advise the system administrator.';
$lang['nomatch']='No match';
$lang['noname']='name missing';
$lang['none']='none';
$lang['nonotifier']='No message can be sent, the relevant system is not installed. Please advise the system administrator.';
$lang['not']='Not';
$lang['noties']='Matches may <strong>not</strong> be tied';
$lang['notified']='Notified';
$lang['notify']='Notify';
$lang['notify_tip']='send match-time message to teams for selected rows';
$lang['notifysel_tip']='send notices about scheduled matches to participants in selected tournaments';
$lang['notsent']='Error during message transmission';
$lang['notyet']='Not yet';
$lang['numloser']='%s loser';
$lang['numother']='%s competitor';
$lang['numwinner']='%s winner';

$lang['one']='One';
$lang['organisers']='the organisers';

$lang['params_chart_css']='Name of uploaded .css file with chart-styling details.';
$lang['params_tmt_alias']='Tournament alias.';
$lang['params_view_type']='Tournament display type: \'chart\' or \'list\'.';
$lang['perm_admin']='Modify Tournament-module Settings';
$lang['perm_mod']='Modify Tournament Brackets';
$lang['perm_score']='Modify Tournament-Bracket Results';
$lang['perm_view']='View Tournament Brackets';
$lang['plan']='Plan';
$lang['plan_tip']='show all matches';
$lang['played']='Played';
$lang['player']='competitor';
$lang['possible']='Maybe';
$lang['prefs_updated']='Settings updated.';
$lang['prevm']='Previous Month';
$lang['print']='Print';
$lang['print_tip']='prepare chart without match-labels';
$lang['printsel_tip']='create plain chart for each selected tournament';
$lang['processed']='This match result has been processed.';

$lang['reporter']='Submitted by %s';
$lang['reset']='Reset';
$lang['reset_tip']='clear and renew all match data';
$lang['result_import_failed']='Result data import failed.';
$lang['result_imported']='Result data imported successfully.';

$lang['save']='Submit';
$lang['saved']='Saved';
$lang['schedule']='Schedule';
$lang['scheduled']='Scheduled';
$lang['score']='Score';
$lang['seeabove']='See corresponding information about announcement-template, above';
$lang['seed_balanced']='More-equal matches eg 1v3';
$lang['seed_none']='Random';
$lang['seed_randbalance']='Random more- or less-equal';
$lang['seed_toponly']='Random except 1,2';
$lang['seed_unbalanced']='Less-equal matches eg 1v4';
$lang['sel_groups']='selected groups'; //inclusion in typed delete-confirm string
$lang['sel_items']='selected tournaments'; //inclusion in typed delete-confirm string
$lang['sel_players']='selected players'; //inclusion in typed delete-confirm string
$lang['sel_teams']='selected teams'; //inclusion in typed delete-confirm string
$lang['select_one']='Select One';
$lang['sentok']='Messages successfully sent';
//shortform daynames - must be Sunday first, comma-separated, no whitespace
$lang['shortdays']='Sun,Mon,Tue,Wed,Thu,Fri,Sat';
//shortform monthnames - must be January first, comma-separated, no whitespace
$lang['shortmonths']='Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec';
$lang['showhelp']='click to toggle display of information about this parameter';
$lang['sort']='Sort';
$lang['status_complete']='Completed';
$lang['status_ended']='Finished';
//in the following, %s will be replaced by 'match' or 'matches'
$lang['status_going']='%d %s complete, %d pending';
$lang['status_notyet']='Not started';
$lang['submit']='Submit';
$lang['submit2']='Submit Result';
$lang['submit_tip']='send result to tournament manager';
$lang['success_import']='Tournament imported successfully.';
$lang['sunrise']='sunrise';
$lang['sunset']='sunset';

$lang['tab_advanced']='Advanced';
$lang['tab_chart']='Chart';
$lang['tab_config']='Settings';
$lang['tab_groups']='Groups';
$lang['tab_items']='Tournaments';
$lang['tab_main']='Tournament';
$lang['tab_matches']='Matches';
$lang['tab_players']='Competitors';
$lang['tab_results']='Results';
$lang['tab_schedule']='Schedule';
$lang['team']='team';
$lang['team_import_failed']='Team data import failed.';
$lang['team_imported']='Team data imported successfully.';
$lang['teamgone']='Withdrew';
$lang['telladmin']='Please advise your site administrator.';
$lang['tied']='Tied';
$lang['title_abandoned']='Result for abandoned match (which has no winner)';
$lang['title_active']='Active';
$lang['title_add']='Add new %s';
$lang['title_add_long']='ADD NEW %s TO %s';
$lang['title_add_tourn']='Add new tournament';
$lang['title_against']='Text for separating opponents';
$lang['title_alias']='Alias name';
$lang['title_atformat']='Format of displayed date & time';
$lang['title_auth']='Authorise tournament-related tweets from a specific twitter account';
$lang['title_avail']='Available';
$lang['title_available']='Match scheduling conditions';
$lang['title_bracket_double']='Double Elimination Tournament';
$lang['title_bracket_round']='Round-Robin Tournament';
$lang['title_bracket_single']='Knockout Tournament';
$lang['title_bracketsgroup']='Group for selected tournaments';
$lang['title_calendar']='Calendar identifier';
$lang['title_cantie']='Matches may be tied';
$lang['title_captcha']='The characters shown in the image above';
$lang['title_changelog']='Logged result-changes for \'%s\' tournament';
$lang['title_changer']='Changer';
$lang['title_changewhen']='When';
$lang['title_chttemplate']='Front-end chart display template';
$lang['title_comment']='Comment';
$lang['title_contact']='Contact details';
$lang['title_contact2']='Contact details for responsible person';
$lang['title_cssfile']='File containing style-parameters';
$lang['title_cssfile2']='File containing style-parameters for %s tournament';
$lang['title_date_format']='Date format';
$lang['title_days']='Day(s)';
$lang['title_defeated']='Text for defeated opponent';
$lang['title_desc']='Description';
$lang['title_edit_long']='UPDATE %s IN %s';
$lang['title_eighth']='Title of match in fourth-last round';
$lang['title_emailcoding']='Email character-encoding';
$lang['title_emailhtml']='Generate HTML email';
$lang['title_end_date']='Preferred finish date';
$lang['title_export_file']='Export to host';
$lang['title_export_file_encoding']='Character-encoding of exported content';
$lang['title_feu_eds']='FEU group whose members can edit tournament results';
$lang['title_final']='Title of final match in the tournament';
$lang['title_fixtype']='Arrangement of other selected competitors';
$lang['title_forfeit']='Result for match won because opponent could not start or finish';
$lang['title_group']='Group';
$lang['title_hours']='Hour(s)';
$lang['title_import']='Import tournament from XML file';
$lang['title_latitude']='Latitude';
$lang['title_locale']='Locale identifier for localising administrative date/time parameters';
$lang['title_logic']='Result validation';
$lang['title_longitude']='Longitude';
$lang['title_mailcanceltemplate']='Match-cancellation email template';
$lang['title_mailintemplate']='Match-result-report email template';
$lang['title_mailouttemplate']='Match-announcement email template';
$lang['title_mailrequesttemplate']='Match-result-request email template';
$lang['title_mid']='Match';
$lang['title_misc_fieldset']='Miscellaneous';
$lang['title_move']='Re-order';
$lang['title_name']='Name';
$lang['title_names_fieldset']='Default Names';
$lang['title_newdata']='After Change';
$lang['title_noop']='Name of non-existent opponent';
$lang['title_olddata']='Before Change';
$lang['title_order']='Order';
$lang['title_ordernum']='Order number';
$lang['title_owner']='Responsible person';
$lang['title_password']='Pass-phrase for securing sensitive data';
$lang['title_phone_regex']='Validator for phone numbers suitable for receiving SMS';
$lang['title_place_gap']='Expected match duration';
$lang['title_play_gap']='Minimum period between matches';
$lang['title_player']='Player';
$lang['title_quarter']='Title of match in third-last round';
$lang['title_result']='Result';
$lang['title_resultimport']='Import match-result data for tournament \'%s\' from CSV file';
$lang['title_roundname']='Title of other matches (will have number appended)';
$lang['title_same_time']='Maximum number of contemporary matches';
$lang['title_scored']='Matches to be scored';
$lang['title_seed']='Seeded';
$lang['title_seedtype']='Arrangement of seeded competitors';
$lang['title_semi']='Title of match in second-last round';
$lang['title_sender']='Your name';
$lang['title_sendto']='Give match-related notices to';
$lang['title_smsfrom']='SMS to tournament participants from';
$lang['title_smsprefix']='Country-prefix for phone numbers to receive SMS messages';
$lang['title_start_date']='Preferred start date';
$lang['title_status']='Status';
$lang['title_strip_on_export']='Strip HTML tags on export';
$lang['title_tag']='Page Tag';
$lang['title_team']='Team';
$lang['title_teamimport']='Import team data for tournament \'%s\' from CSV file';
$lang['title_teamname']='Team name';
$lang['title_teams']='Teams';
$lang['title_teamsize']='Players per team';
$lang['title_tied']='Text for tied match';
$lang['title_time_format']='Time format';
$lang['title_title']='Displayed title';
$lang['title_tnmt_oldname']='Cloned Tournament';
$lang['title_tweetcanceltemplate']='Match-cancellation text/tweet template';
$lang['title_tweetintemplate']='Match-result-report text/tweet template';
$lang['title_tweetouttemplate']='Match-announcement text/tweet template';
$lang['title_tweetrequesttemplate']='Match-result-request text/tweet template';
$lang['title_twtfrom']='Tweets about the tournament posted by';
$lang['title_type']='Type';
$lang['title_uploads_dir']='Sub-directory for module-specific file uploads';
$lang['title_venue']='Venue';
$lang['title_weeks']='Week(s)';
$lang['title_when']='Completed at';
$lang['title_zone']='Time zone';
$lang['tournament']='Tournament';
$lang['tpl_mailresult']='Please send the match result to %s'; //email %s replaced by template code
$lang['tpl_tweetresult']='Send result to %s'; //tweet/SMS %s replaced by template code

$lang['unrestricted']='No restriction';
$lang['up']='Move up';
$lang['update']='Update';
$lang['update_tip']='save data for selected rows';
$lang['updated']='Updated.';
$lang['updated2']='Updated %s.';
$lang['updateselgrp']='update selected groups';
$lang['upload']='Upload';
$lang['upload_tip']='upload selected file to website host';

$lang['view']='View';

$lang['week']='week'; //so we can skip parsing ['periods'] sometimes

$lang['yes']='Yes';
$lang['yesties']='Matches may be tied'; //c.f. above

//$lang['administer']='Administer';
//$lang['alias']='Alias';
//$lang['allmatchsaved']='Are all matches\\\' data saved ?'; //double-escaped for js inclusion
//$lang['any']='Any';
//$lang['err_data']='Data processing error';
//$lang['export_selected_records']='export selected records';
//$lang['forfeited']='Forfeited';
//$lang['help_bracketsgroup']='';
//$lang['help_css_class']='Optional name of class, or space-separated series of class names, applied to list views';
//$lang['sortselgrp']='sort selected groups by name';
//$lang['status_started']='In progress';
//$lang['tab_admin_comp']='Admin View';
//$lang['tab_admin_options']='Admin Options';
//$lang['tab_user_comp']='Frontend View';
//$lang['tab_user_options']='Frontend Options';
//$lang['title_admin_eds']='Admin users group whose members can edit tournament results';
//$lang['title_css_class']='CSS Class';
//$lang['title_month']='Month';
//$lang['title_seednum']=; see title_seed
//$lang['title_year']='Year';
//$lang['type']='Type';
//$lang['update']='Update Tournament';
//$lang['won']='Won';
//$lang['won']='won';

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

$lang['help_logic']=<<<EOS
Javascript/jquery to be executed before submitting a match result.<br />
This code is embedded in a js function, and must return false if validation fails.
EOS;
$lang['help_locale']=<<<EOS
A 'locale' is an identifier which can be used to get language-specific terms.
Examples are 'en_US' and 'cs_CZ.UTF-8' and 'zh_Hant_TW'.
Refer to <a href="https://www.gnu.org/software/gettext/manual/html_node/Locale-Names.html">this reference</a> for more details.
Blank means use default.
EOS;
$lang['help_seednum']=<<< EOS
Optional number, 1 .. whatever or -1 .. -whatever.
When determining first-match opponents, competitors assigned a seed < 0
are collectively treated as an independent sub-section of the tournament,
and in accord with its 'other selected competitors' setting.
EOS;
$lang['help_order']=<<< EOS
Competitors are ordered by this number, on the tournament's competitors tab.
You may enter -1 to place this competitor first, leave blank to place it last.
The displayed order has no effect on opponent determination, but may effect scheduling.
EOS;

$lang['help_available']=<<< EOS
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
Any P may be prefaced by 'not' or 'except' to specify a period to be excluded from
the period(s) otherwise covered by other period-descriptors.<br />
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

$lang['help_cssupload']=<<< EOS
<h3>File Format Information</h3>
<p>The file must be in ASCII stylesheet format. Module help includes details of file content.</p>
<h3>Problems</h3>
<p>The upload process will fail if:<ul>
<li>the file does not look like a relevant stylesheet</li>
<li>the file-size is bigger than about 2 kB</li>
<li>filesystem permissions are insufficient</li>
</ul></p>
EOS;

$lang['help_resultimport']=<<< EOS
<h3>File Format Information</h3>
<p>The input file must be in ASCII format with data fields separated by commas.
Any actual comma in a field should be represented by '&amp;#44;'.
Each line in the file (except the header line, discussed below) represents one match.</p>
<h4>Header Line</h4>
<p>The first line of the file names the fields in the file. They must be
'Bracket','Match','CompetitorA','CompetitorB','Result','Score', and 'Finished' (any order, no quotes, not translated).
<h4>Other Lines</h4>
<p>The data in each line must conform to the header columns, of course. Any line may be empty.</p>
<p>The bracket-field must contain an adequate identifier - the relevant id-number, alias, or title.</p>
<p>The match-field should contain the relevant id-number, or else if it's empty,
an attempt will be made to identify the relevant match from the supplied competitor-names.</p>
<p>The competitor-fields must identify at least one (if the match-id is provided, at least the winner) of the competitors, matching exactly the names displayed in bracket data.</p>
<p>The result-field must identify which of the competitors (if any) prevailed, by 'win A', 'win B', 'draw', 'tie', or 'abandon' (no quotes, not translated)</p>
<p>The score field may be, or include, 'forfeit' (no quotes, not translated)</p>
<p>The finished-field may be a time (as HH:MM) in which case the day will be assumed to be the scheduled day.
Otherwise a full datestamp (as YYYY-MM-DD HH:MM) may be provided. Or if empty, an approximate date/time will be determined.</p>
<h3>Problems</h3>
<p>The import process will fail if:<ul>
<li>the field names are are not as expected</li>
<li>there are fewer fields in any line of data than there are fieldnames in the header line</li>
<li>the data in any field does not meet the conditions set out above</li>
<li>the data-file size is bigger than 2048 bytes</li>
</ul></p>
EOS;

$lang['help_teamimport']=<<< EOS
<h3>File Format Information</h3>
<p>The input file must be in ASCII format with data fields separated by commas.
Any actual comma in a field should be represented by '&amp;#44;'.
Each line in the file (except the header line, discussed below) represents one team.</p>
<h4>Header Line</h4>
<p>The first line of the file names the fields in the file. First, there may be up to three
optional fields, named '#Teamname' and/or '#Seeded' and/or '#Tellall' (no quotes, any order).
Further fieldnames, if they exist, can have any names but must be in trio's -
the first of each to hold a player name, the second to hold contact information for that player,
the third may either hold an availabilty descriptor for that player, or be empty if there is no constraint.
There may be any number of such trios. For example:<br />
<code>#Seeded,Player,Contact</code> or<br />
<code>#Seeded,#Teamname,#Tellall,Captain,Contact1,Avail1,Player2,Contact2,Avail2</code></p>
<h4>Other Lines</h4>
<p>The data in each line must conform to the header columns, of course. Any field, or entire line, may be empty.
The tellall field will be treated as TRUE if it contains something other than '0' or 'no' or 'NO' (no quotes, untranslated).</p>
<h3>Problems</h3>
<p>The import process will fail if:<ul>
<li>the first one, two or three '#'-prefixed field names are are not as expected</li>
<li>the number of player-specific fields is not a multiple of 3</li>
<li>there are fewer fields in any line of data than there are fieldnames in the header line</li>
</ul></p>
<h3>You Decide What to Keep</h3>
<p>Imported data are <strong>not automatically stored</strong> in the database.
After review and any modification, you should save the tournament data in the normal manner.;
EOS;

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
Each tag looks something like <pre>{Tourney alias='sample_comp'}</pre></p>
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
Chart can have pseudo-properties 'gapwidth', representing extra horizontal distance between boxes' margins,
and 'minheight', 'minwidth', representing a minimum page size for printed/plain charts.
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
<h3>Requirements</h3>
<ul>
<li>PHP 5.3.6+</li>
</ul>
<h3>Support</h3>
<p>This module is provided as-is. Please read the text of the license for the full disclaimer.</p>
<p>For help:<ul>
<li>discussion may be found in the <a href="http://forum.cmsmadesimple.org">CMS Made Simple Forums</a>; or</li>
<li>you may have some success emailing the author directly.</li>
</ul></p>
<p>For the latest version of the module, or to report a bug, visit the module's <a href="http://dev.cmsmadesimple.org/projects/tourney">forge-page</a>.</p>
<h3>Copyright and License</h3>
<p>Copyright &copy; 2014-2016 Tom Phane. All rights reserved.</p>
<p>This module has been released under version 3 of the <a href="http://www.gnu.org/licenses/agpl.html">GNU Affero General Public License</a>, and may be used only in accordance with the terms of that licence, or any later version of that license which is applied to the module.<br />
The included fonts have been licensed by <a href="http://www.urwpp.de/english/home.html">URW</a> under the GPL.</p>
EOS;
?>
