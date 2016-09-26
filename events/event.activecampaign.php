<?php

class eventactivecampaign extends Event
{
    public $ROOTELEMENT = 'activecampaign';

    public $eParamFILTERS = array(
        // 'etm-get-in-touch'
    );

    public static function about()
    {
        return array(
            'name' => 'Active Campaign',
            'author' => array(
                'name' => 'Jonathan Mifsud',
                'website' => 'http://maze.dev',
                'email' => 'jonathan@maze.digital'),
            'version' => 'Symphony 2.6.0',
            'release-date' => '2015-05-23T12:33:03+00:00',
            'trigger-condition' => 'action[activecampaign-*]'
        );
    }

    public static function getSource()
    {
        return '3';
    }

    public static function allowEditorToParse()
    {
        return true;
    }

    public static function documentation()
    {
        return '
                <h3>Success and Failure XML Examples</h3>
                <p>When saved successfully, the following XML will be returned:</p>
                <pre class="XML"><code>&lt;get-in-touch result="success" type="create | edit">
    &lt;message>Entry [created | edited] successfully.&lt;/message>
&lt;/get-in-touch></code></pre>
                <p>When an error occurs during saving, due to either missing or invalid fields, the following XML will be returned.</p>
                <pre class="XML"><code>&lt;get-in-touch result="error">
    &lt;message>Entry encountered errors when saving.&lt;/message>
    &lt;field-name type="invalid | missing" />
...&lt;/get-in-touch></code></pre>
                <p>The following is an example of what is returned if any options return an error:</p>
                <pre class="XML"><code>&lt;get-in-touch result="error">
    &lt;message>Entry encountered errors when saving.&lt;/message>
    &lt;filter name="admin-only" status="failed" />
    &lt;filter name="send-email" status="failed">Recipient not found&lt;/filter>
...&lt;/get-in-touch></code></pre>
                <h3>Example Front-end Form Markup</h3>
                <p>This is an example of the form markup you can use on your frontend:</p>
                <pre class="XML"><code>&lt;form method="post" action="{$current-url}/" enctype="multipart/form-data">
    &lt;input name="MAX_FILE_SIZE" type="hidden" value="2097152" />
    &lt;label>Name
        &lt;input name="fields[first_name]" type="text" />
    &lt;/label>
    &lt;label>Surname
        &lt;input name="fields[last_name]" type="text" />
    &lt;/label>
    &lt;label>Email
        &lt;input name="fields[email]" type="text" />
    &lt;/label>
    &lt;label>Phone
        &lt;input name="fields[phone]" type="text" />
    &lt;/label>
    &lt;label>Company
        &lt;input name="fields[company]" type="text" />
    &lt;/label>
    &lt;label>Budget
        &lt;input name="fields[budget]" type="text" />
    &lt;/label>
    &lt;label>Comments
        &lt;textarea name="fields[comments]" rows="15" cols="50">&lt;/textarea>
    &lt;/label>
    &lt;input name="action[get-in-touch]" type="submit" value="Submit" />
&lt;/form></code></pre>
                <p>To edit an existing entry, include the entry ID value of the entry in the form. This is best as a hidden field like so:</p>
                <pre class="XML"><code>&lt;input name="id" type="hidden" value="23" /></code></pre>
                <p>To redirect to a different location upon a successful save, include the redirect location in the form. This is best as a hidden field like so, where the value is the URL to redirect to:</p>
                <pre class="XML"><code>&lt;input name="redirect" type="hidden" value="http://maze.dev/success/" /></code></pre>';
    }

    private function __subscribe(){

        $activeCampaign = ExtensionManager::getInstance('activecampaign');
        $ac = $activeCampaign->getActiveCampaign();

        $fields = $_POST["fields"];

        // var_dump($fields);die;

        // $_POST["first_name"] = "";
        // $_POST["last_name"] = "";
        // $_POST["p"] = array($listid => $listid);
        // $_POST["status"] = array($listid => 1);
        // $_POST["instantresponders"] = array(3 => 1);



        $apiResult = $ac->api("contact/add", $fields);

        if ($apiResult->success == 1){
            return new XMLElement(
                            'activecampaign-subscribe',
                            null,
                            array(
                                'result'=>'success',
                                'subscriber_id'=>$apiResult->subscriber_id
                            )
                        );
        } else if ( $apiResult->success == 0 ){
            var_dump($apiResult[0]);die;
        }

        $ac->dbg($apiResult);
        die;

    }


    public function load()
    {
        if (isset($_POST['action']['activecampaign-subscribe'])) {
            return $this->__subscribe();
        }
        if (isset($_POST['automation'])) {
            return $this->__subscribe();
        }
    }

}
