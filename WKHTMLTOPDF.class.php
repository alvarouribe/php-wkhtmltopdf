<?php
/******************************************************************************
* Copyright (c) 2012 Ariful Islam
* 
* Permission is hereby granted, free of charge, to any person obtaining
* a copy of this software and associated documentation files (the
* "Software"), to deal in the Software without restriction, including
* without limitation the rights to use, copy, modify, merge, publish,
* distribute, sublicense, and/or sell copies of the Software, and to
* permit persons to whom the Software is furnished to do so, subject to
* the following conditions:
* 
* The above copyright notice and this permission notice shall be
* included in all copies or substantial portions of the Software.
* 
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
* EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
* MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
* NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
* LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
* OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
* WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*******************************************************************************/

/**
 * EXAMPLE USAGE
 * 
 * Usage 1 (static html): 
 * 
 *      $pdf = new WKHTMLTOPDF('WKPDF Test', 'P');
 *      $pdf->setFontSize(WKHTMLTOPDF::FONT_SIZE_MEDIUM);   //Optional. defaults to FONT_SIZE_SMALL
 *      $pdf->setStyle('td{color:red}')                     //Optional
 *      $pdf->setContent('you html goes here'); 
 *      $pdf->generate('test.pdf', true, true);
 *  
 * Usage 2 (dynamic html/table):
 *      
 *      //Generating dynamic content-------------------------------------------------------
 *      
 *      $rec = array();
 *      
 *      for($i=1; $i<=2000; $i++){
 *          $rec[] = array(
 *              'First_name'=>'first_name '.$i, 
 *              'Last_name'=>'last_name_'.$i, 
 *              'Count'=>$i,
 *              'Age'=>'age_'.$i,
 *              'Email'=>'my_email_add_'.$i.'@foo.com',
 *              'description'=>'dasfasf  dasf'
 *          );
 *      } 
 *      
 *      $header = array(
 *          array("name"=>"First Name", "dataIndex"=>"First_name", "charLimit"=>15),
 *          array("name"=>"Last Name", "dataIndex"=>"Last_name", "charLimit"=>15),
 *          array("name"=>"Count", "dataIndex"=>"Count", "charLimit"=>5),
 *          array("name"=>"Age", "dataIndex"=>"Age", "charLimit"=>5),
 *          array("name"=>"Email", "dataIndex"=>"Email", "charLimit"=>15),
 *          array("name"=>"Description", "dataIndex"=>"description", "charLimit"=>20)
 *      );
 * 
 *      // Here charLimit is important when you know that a column text on your table may wrap inside the cell.
 *      // Specify the max number of characters allowed in one line on a table column in charLimit. This will force a text wrap
 *      // (without breaking in the middle of a word) when max charLimit is reached . The idea here is to keep track of your 
 *      // text wrap so that you can adjust the number of rows allowed per page. The key to taking the control over text wrap 
 *      // from wkhtmltopdf generator so that you can account for each and every wrapped line.
 *      //---------------------------------------------------------------------------------
 * 
 *      $pdf = new WKHTMLTOPDF('WKPDF Test', 'P');
 *      $pdf->setFontSize(WKHTMLTOPDF::FONT_SIZE_MEDIUM);
 *      $pdf->makeTableHeader($header);
 *      $pdf->makeTableBody($rec);
 *      $pdf->generate('test.pdf', true, true);
 */
class WKHTMLTOPDF {
    const WKHTMLTOPDF_APP = '/usr/lib/wkhtmltopdf/wkhtmltopdf-i386';
    const TEMP_FILE_PATH = '/var/www/includes/lib/wkhtmltopdf/temp/';
    const GENERATED_BY_NAME = 'PHP-WKHTMLTOPDF';
    
    /**
     * PDF page orientation.
     *
     * @access protected
     * @var string
     */
    protected $orientation;
    /**
     * PDF page size.
     *
     * @access protected
     * @var string
     */
    protected $pageSize;
    /**
     * The html source code or a url to generate into a PDF.
     *
     * @access protected
     * @var string
     */
    protected $source;
    /**
     * The type of source being either url or html.
     *
     * @access protected
     * @var string
     */
    protected $sourceType;
    /**
     * The file path for the temporary file used when generating from html.
     *
     * @access protected
     * @var string
     */
    protected $tmpFile = '';
    /**
     * The file path for the PDF.
     *
     * @access protected
     * @var string
     */
    protected $writePath = '';
    
    /**
     * PDF title
     * @var string 
     */
    protected $title;
    
    /**
     * PDF table header
     * @var array 
     */
    protected $header;
    
    /**
     * PDF font size
     * @var string
     */
    protected $fontSize;
    
    /**
     * PDF CSS styles 
     * @var array 
     */
    protected $styles = array();

    /********************************************************************************************************************
     * This is the most critical configuration area of this PHP interface to wkhtmltopdf generator.
     * wkhtmltopdf does not support auto table break (on page breaks) for long tables. As a result
     * a long table gets printed on multiple pages without properly breaking the table (putting table 
     * header at the beginning of each page) on each page. However wkhtmltopdf does support force 
     * page break (using css) and therefore developers can handle long tables by inserting a force page
     * break where appropriate. This works well for a static table where the developers know for sure
     * that thier table is going to take 'X' number of pages. But it does not work when the table is 
     * dynamic and the developers don't exactly know how many pages their table will take. This is the main 
     * reason for me to write this PHP interface for wkhtmltopdf so that it can automatically break a 
     * long table properly (with header) on each page. This is done by simply counting how many rows
     * a page can fit and then break it accordingly. Since the number of rows will depend on the size of font 
     * and the type of font, I have added three font size configuration for this for the DEFAULT wkhtmltopdf font type. 
     * If you need to use a different font size/type you need to add/adjust the following configuration accordingly.
     */
    const FONT_SIZE_SMALL = '12px';
    const FONT_SIZE_MEDIUM = '14px';
    const FONT_SIZE_LARGE = '20px';
    
    protected $rowCountConfig = array(
        self::FONT_SIZE_SMALL=>array(
            'row_per_page'=>array(
                'Landscape'=>40, 
                'Portrait'=>54, 
                'WrappedLineCount'=>0.9)
        ),
        self::FONT_SIZE_MEDIUM=>array(
            'row_per_page'=>array(
                'Landscape'=>36, 
                'Portrait'=>48, 
                'WrappedLineCount'=>0.9)
        ),
        self::FONT_SIZE_LARGE=>array(
            'row_per_page'=>array(
                'Landscape'=>26, 
                'Portrait'=>35, 
                'WrappedLineCount'=>0.9)
        ),
    );
    /**********************************************************************************************************************/
    
    /**
     * Basic constructor.
     * 
     * @param string $title PDF title populates {@link $title}
     * @param string $orientation populates {@link $orientation}
     * @param string $fontSize populates {@link $fontSize}
     * @param string $sourceType populates {@link $sourceType}
     */
    public function __construct($title, $orientation = 'P', $fontSize=self::FONT_SIZE_SMALL, $sourceType='html') {
        $this->title = $title;
        $this->orientation = 'Portrait';
        if($orientation=='L'){
            $this->orientation = 'Landscape';
        }
        $this->sourceType = $sourceType;
        $this->pageSize = 'Letter';
        $this->writePath = self::TEMP_FILE_PATH;
        $this->setFontSize($fontSize);
    }
    
    /**
     * Sets the font size
     * 
     * @param string $fontSize Font size use (only accepts the configured font sized in this class)
     * @throws UnexpectedValueException 
     */
    public function setFontSize($fontSize){
        if(!in_array($fontSize, array(self::FONT_SIZE_LARGE, self::FONT_SIZE_MEDIUM, self::FONT_SIZE_SMALL))){
            throw new UnexpectedValueException(__METHOD__.": Invalid Font Size Supplied.");
        }
        $this->fontSize = $fontSize;
    }
    
    /**
     * Sets PDF style
     * 
     * @param mixed $style PDF styles.
     */
    public function setStyle($style){
        $styles = array();
        if(!is_array($style)) $styles[] = $style;
        
        $this->styles = array_merge($this->styles, $styles);
    }
    
    /**
     * Sets the PDF content
     * 
     * @param string $source
     */
    public function setContent($source){
        $this->source = $source;
    }

    /**
     * Creates table header
     * @param array $header Header config array
     */
    public function makeTableHeader(array $header){
        $this->header = $header;
    }
    
    /**
     * Creates table body
     * @param array $records Table body content
     */
    public function makeTableBody(array $records){
        $rpp = $this->rowCountConfig[$this->fontSize]['row_per_page'][$this->orientation];
        $wlc = $this->rowCountConfig[$this->fontSize]['row_per_page']['WrappedLineCount'];
        
        $table = '';
        $rowPrinted = 0;
        $table .= $this->beginTable();
        $table .= $this->generateThead();
        foreach($records as $row){
            $thisRow = '<tr>';
            $lineCount=1;
            foreach($this->header as $h){
                foreach($row as $k=>$v){
                    if($h['dataIndex']==$k){
                        $v = wordwrap($v, $h['charLimit'], '<br>');
                        $columnLineCount = count(explode('<br>', $v))*$wlc;
                        $lineCount = ($columnLineCount>$lineCount)? $columnLineCount : $lineCount;
                        $thisRow .= '<td>'.$v.'</td>';
                    }
                }
            }
            $thisRow .= '</tr>';
            $rowPrinted = $rowPrinted+$lineCount;
            
            if($rowPrinted==$rpp){
                $table .= $thisRow;
                $rowPrinted = 0;
                $table .= $this->endTable();
                $table .= $this->beginTable();
                $table .= $this->generateThead();
            }
            else if($rowPrinted>$rpp){
                $rowPrinted = $lineCount;
                $table .= $this->endTable();
                $table .= $this->beginTable();
                $table .= $this->generateThead();
                $table .= $thisRow;
            }
            else{
                $table .= $thisRow;
            }
        }
        $table .= $this->endTable();
        $this->source .= $table;
    }
    
    /**
     * Destruct deletes the temporary file if it was created.
     */
    public function __destruct() {
        if (file_exists($this->tmpFile)) {
            $this->destroyTmpFile();
        }
    }

    /**
     * The generate method figures out what is needed based on the
     * {@link $sourceType} and then runs the command to wkhtmltopdf.
     *
     * @param string $pdfFileName the filename to use for the pdf.
     * @param boolean $download (Optional) Set to true to download file after generation. Defaults to false.
     * @param boolean $deleteAfterDownload (Optional) Set to true to delete PDF file from server after downloading it. Defaults to false.
     * @return string the pdf's final path.
     */
    public function generate($pdfFileName, $download=false, $deleteAfterDownload=false) {
        $this->makeHTML();
        switch ($this->sourceType) {
            case 'html':
                $sourcePath = $this->createTmpFile($this->source);
                break;
            case 'url':
                $sourcePath = $this->source;
                break;
            default:
                throw new Exception('Unknown Source Type: ' . $this->sourceType);
        }
        
        $parameters = array(
            '-O ' . $this->orientation,
            '-q',
            '--header-left "[title]"',
            '--header-line',
            '--header-spacing 3',
            '--footer-line',
            '--footer-font-size 10',
            '--footer-left "Generate by '.self::GENERATED_BY_NAME.', Date: [date] [time]"',
            '--footer-right "Page [page] of [topage]"',
            '--page-size ' . $this->pageSize
        );
        
        $pdfFilePath = $this->writePath . $pdfFileName;
        $cmd = self::WKHTMLTOPDF_APP .' '. implode(' ', $parameters) . ' ' . $sourcePath . ' ' . $pdfFilePath;
        $output = array();
        exec($cmd, $output);
        
        if($download){
            $file = self::TEMP_FILE_PATH.$pdfFileName;
        
            $reader = fopen($file, "r");
            $contents = fread($reader, filesize($file));
            header('Content-Description: File Download');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename='.$pdfFileName);
            echo $contents;
            if($deleteAfterDownload) unlink($file);
        }
        else{
            return $pdfFileName;
        }
    }
    
    /**
     * Begins a table
     * @return string 
     */
    protected function beginTable(){
        return '<div class="page"><table width="100%">';
    }
    
    /**
     * Ends a table
     * @return string 
     */
    protected function endTable(){
        return '</table></div>';
    }
    
    /**
     * Creates the HTML content when generating dynamic table 
     */
    protected function makeHTML(){
        $html = '<html>';
        $html .= '<head>';
        $html .= '<title>'.$this->title.'</title>';
        $html .= $this->makeStyle();
        $html .= '</head>';
        $html .= '<body>'.$this->source.'</body>';
        $html .= '</html>';
        $this->source = $html;
    }

    /**
     * Creates PDF styles
     * @return string 
     */
    protected function makeStyle(){
        $baseStyles = $this->getBaseStyles();
        $allStyles = array_merge($baseStyles, $this->styles);
        $styleString = '<style>';
        foreach($allStyles as $style){
            $styleString .= $style.' ';
        }
        $styleString .= '</style>';
        return $styleString;
    }


    /**
     * The method to create the temporary html file.
     *
     * @access protected
     * @param string $html the html source code to turn into a file.
     * @param string $filePrefix (Optional) A prefix (followed by an _) to attach to the temporarily file name. Defaults to 'temp'
     * @return string the filename of the temporary file.
     */
    protected function createTmpFile($html, $filePrefix='temp') {
        $filename = $this->writePath . $filePrefix.'_'.time() . '.html';
        $fh = fopen($filename, 'w');
        fwrite($fh, $html);
        fclose($fh);
        $this->tmpFile = $filename;
        return $filename;
    }

    /**
     * Destroys the temporary html file.
     */
    protected function destroyTmpFile() {
        unlink($this->tmpFile);
    }

    /**
     * Generates the base styles necessary to generate PDF properly
     * @return type 
     */
    protected function getBaseStyles(){
        $fontStyle = 'body, tr, td, th{ font-size: '.$this->fontSize.'; }';
        $tableStyle = 'table{ border-collapse:collapse; } table, td, th{ border:1px solid black; }';
        $pageStyle = '.page{ display: block; clear: both; page-break-after: always; }';
        
        return array($fontStyle, $tableStyle, $pageStyle);
    }
    
    /**
     * Generates Table header
     * @return string
     * @throws UnexpectedValueException 
     */
    protected function generateThead(){
        if(count($this->header)==0) throw new UnexpectedValueException(__METHOD__.": No table header information supplied.");
        
        $theadString = '<thead><tr>';
        foreach($this->header as $h){
            $theadString .= '<th>'.$h['name'].'</th>';
        }
        $theadString .= '</tr></thead>';
        
        return $theadString;
    }
}
?>
