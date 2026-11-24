using System.Collections.Generic;
using System.Net;
using System.Text;

namespace LsPaySdk;

/// <summary>pay_data_type=cashier 的收银台表单：由用户浏览器把 Fields 以 POST 提交到 Action。</summary>
public sealed class CashierForm
{
    internal CashierForm(string action, IDictionary<string, string> fields)
    {
        Action = action;
        Fields = new Dictionary<string, string>(fields);
    }

    public string Action { get; }
    public string Method => "POST";
    public IReadOnlyDictionary<string, string> Fields { get; }

    /// <summary>自动提交到立收通收银台的 HTML 表单。</summary>
    public string ToHtml()
    {
        var html = new StringBuilder();
        html.Append("<form id=\"lspayCashierForm\" method=\"POST\" action=\"").Append(WebUtility.HtmlEncode(Action)).Append("\">");
        foreach (var field in Fields)
            html.Append("<input type=\"hidden\" name=\"").Append(WebUtility.HtmlEncode(field.Key))
                .Append("\" value=\"").Append(WebUtility.HtmlEncode(field.Value)).Append("\">");
        return html.Append("</form><script>document.getElementById(\"lspayCashierForm\").submit();</script>").ToString();
    }
}
