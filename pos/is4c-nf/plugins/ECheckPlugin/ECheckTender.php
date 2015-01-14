<?php
/*******************************************************************************

    Copyright 2014 Whole Foods Co-op

    This file is part of IT CORE.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

/**
  @class ECheckTender
  Tender module for handling both paper
  and electronic checks
*/
class ECheckTender extends TenderModule 
{

    /**
      Check for errors
      @return True or an error message string
    */
    public function errorCheck()
    {
        if ( CoreLocal::get("isMember") != 0 && (($this->amount - CoreLocal::get("amtdue") - 0.005) > CoreLocal::get("dollarOver")) && (CoreLocal::get("cashOverLimit") == 1)) {
            return DisplayLib::boxMsg(_("member check tender cannot exceed total purchase by over $").CoreLocal::get("dollarOver"));
        } else if( CoreLocal::get("store")=="wfc" && CoreLocal::get("isMember") != 0 && ($this->amount - CoreLocal::get("amtdue") - 0.005) > 0) { 
            // This should really be a separate tender 
            // module for store-specific behavior
            $db = Database::pDataConnect();
            $q = sprintf("SELECT card_no FROM custReceiptMessage
                WHERE card_no=%d AND modifier_module='WfcEquityMessage'",
                CoreLocal::get('memberID'));
            $r = $db->query($q);
            if ($db->num_rows($r) > 0) {
                return DisplayLib::xboxMsg(_('member check tender cannot exceed total 
                                    purchase if equity is owed'));
            }

            // multi use
            if (CoreLocal::get('standalone')==0) {
                $chkQ = "select trans_num from dlog 
                    where trans_type='T' and trans_subtype in ('CA','CK') 
                    and card_no=".((int)CoreLocal::get('memberID'))."
                    group by trans_num 
                    having sum(case when trans_subtype='CK' then total else 0 end) < 0 
                    and sum(Case when trans_subtype='CA' then total else 0 end) > 0";
                $db = Database::mDataConnect();
                $chkR = $db->query($chkQ);
                if ($db->num_rows($chkR) > 0) {
                    return DisplayLib::xboxMsg(_('already used check over benefit today'));
                }
            }
        } else if( CoreLocal::get("isMember") == 0  && ($this->amount - CoreLocal::get("amtdue") - 0.005) > 0) { 
            return DisplayLib::xboxMsg(_('non-member check tender cannot exceed total purchase'));
        }

        return true;
    }
    
    /**
      Set up state and redirect if needed
      @return True or a URL to redirect
    */
    public function preReqCheck()
    {
        /**
          First prompt: choose check type
        */
        if (CoreLocal::get('msgrepeat') == 0) {
            CoreLocal::set('strEntered', ($this->amount*100) . $this->tender_code);
            CoreLocal::set('lastRepeat', 'echeckVerifyType');
            $plugin_info = new ECheckPlugin();
            return $plugin_info->plugin_url() . '/ECheckVerifyPage.php?amount='.$this->amount;;
        } else if (CoreLocal::get('msgrepeat') == 1 && CoreLocal::get('lastRepeat') == 'echeckVerifyType') {
            CoreLocal::set('msgrepeat', 0);
            CoreLocal::set('lastRepeat', '');
        }

        /**
          If paper check, endorsing prompt
        */
        if ($this->tender_code == 'CK' && CoreLocal::get('enableFranking') == 1) {
            if (CoreLocal::get('msgrepeat') == 0) {
                CoreLocal::set('lastRepeat', 'echeckEndorse');
                return $this->endorsing();
            } else if (CoreLocal::get('msgrepeat') == 1 && CoreLocal::get('lastRepeat') == 'echeckEndorse') {
                CoreLocal::set('msgrepeat', 0);
                CoreLocal::set('lastRepeat', '');
            }
        }

        return true;
    }

    /**
      Setup session data & prompt strings
      for check endorsing
    */
    protected function endorsing()
    {
        $ref = trim(CoreLocal::get("CashierNo"))."-"
            .trim(CoreLocal::get("laneno"))."-"
            .trim(CoreLocal::get("transno"));

        if ($this->amount === False) {
            $this->amount = $this->defaultTotal();
        }

        $msg = "<br />"._("insert")." ".$this->name_string.
            ' for $'.sprintf('%.2f',$this->amount).
            "<br />"._("press enter to endorse");
        $msg .= "<p><font size='-1'>"._("clear to cancel")."</font></p>";
        if (CoreLocal::get("LastEquityReference") == $ref) {
            $msg .= "<div style=\"background:#993300;color:#ffffff;
                margin:3px;padding: 3px;\">
                There was an equity sale on this transaction. Did it get
                endorsed yet?</div>";
        }

        CoreLocal::set("boxMsg",$msg);
        CoreLocal::set('strEntered', (100*$this->amount).$this->tender_code);

        return MiscLib::base_url().'gui-modules/boxMsg2.php?endorse=check&endorseAmt='.$this->amount;
    }

}

