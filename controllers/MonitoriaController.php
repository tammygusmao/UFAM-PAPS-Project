<?php

namespace app\controllers;

use Yii;
use app\models\Monitoria;
use app\models\MonitoriaSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\Disciplina;
use app\models\DisciplinaSearch;
use yii\helpers\ArrayHelper;
use yii\db\Command;
use yii\filters\AccessControl;
use yii\web\UploadedFile;
use yii\helpers\Time;
use app\models\PeriodoInscricaoMonitoria;
use app\models\DisciplinaPeriodo;
use app\models\DisciplinaPeriodoSearch;
use app\models\Usuario;
use app\models\DisciplinaMonitoria;
use app\models\DisciplinaMonitoriaSearch;
use app\models\AlunoMonitoria;
use app\models\AlunoMonitoriaSearch;
use app\models\Periodo;
use app\models\Curso;
use mPDF;

/**
 * MonitoriaController implements the CRUD actions for Monitoria model.
 */
class MonitoriaController extends Controller
{
    public function behaviors()
    {
        return [
            'acess' => [
                'class' => AccessControl::className(),
                'only' => ['create','index','update', 'view', 'delete', 'inscricaoaluno', 'inscricaosecretaria'],
                'rules' => [
                    [
                        'actions' => ['create','index','update', 'view', 'delete', 'inscricaoaluno', 'inscricaosecretaria'],
                        'allow' => true,
                        'matchCallback' => function ($rule, $action) 
                        {
                            if (!Yii::$app->user->isGuest)
                            {
                                if ( Yii::$app->user->identity->perfil === 'Secretaria' ) 
                                {
                                    return Yii::$app->user->identity->perfil == 'Secretaria'; 
                                }
                                else  return Yii::$app->user->identity->perfil == 'Aluno'; 
                            }
                            //else -> redirecionar para o site/cpf
                        }
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Lists all Monitoria models.
     * @return mixed
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    /**
     * Displays a single Monitoria model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => AlunoMonitoria::findOne($id),
        ]);
    }

    /**
     * Creates a new Monitoria model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Monitoria();

        if ($model->load(Yii::$app->request->post())) {

            //$attributes = ['IDDisc'];
            //if ($model->validate($attributes)) {

            if (Yii::$app->request->post('step') == '1') {

                //Usuario - Pega aluno baseando-se no CPF do usuário logado
                $aluno = Usuario::findOne(['CPF' => Yii::$app->user->identity->cpf]);
                $model->IDAluno = $aluno->id;

                //Status - Aguardando Avaliação
                $model->status = 0;

                //Seleciona o último período de inscrição
                $periodoInscricao = PeriodoInscricaoMonitoria::find()->orderBy(['id' => SORT_DESC])->one();
                $model->IDperiodoinscr = $periodoInscricao->id;
                
                //$model->IDDisc = Yii::$app->request->post('id');
                //$monitoria = DisciplinaMonitoria::find()->where(['id' => Yii::$app->request->post('id')])->one();
                $monitoria = DisciplinaMonitoria::find()->where(['id' => $model->IDDisc])->one();
                $model->nomeDisciplina = $monitoria->nomeDisciplina;
                $model->nomeProfessor = $monitoria->nomeProfessor;

                return $this->render('create', [
                    'model' => $model,
                    'etapa' => '2',
                    'periodo' => $periodoInscricao->ano .'/'. $periodoInscricao->periodo,
                    'matricula' => $aluno->matricula,
                ]);

            } else if (Yii::$app->request->post('step') == '2') {

                //Usuario - Pega aluno baseando-se no CPF do usuário logado
                $aluno = Usuario::findOne(['CPF' => Yii::$app->user->identity->cpf]);

                //Arquivo Histórico
                //Habilitar "extension=php_fileinfo.dll" em C:\xampp\php\php.ini
                $model->file = UploadedFile::getInstance($model, 'file');
                //$model->file->saveAs('uploads/historicos/'.$aluno->matricula.'_'.date('Ydm_His').'.'.$model->file->extension);
                $model->pathArqHistorico = 'uploads/historicos/'.$aluno->matricula.'_'.date('Ydm_His').'.'.$model->file->extension;
                //$model->file = 'uploads/historicos/'.$aluno->matricula.'_'.date('Ydm_His').'.'.$model->file->extension;

                $model->datacriacao = date('Y-d-m H:i:s');

                //if ($model->validate()) {
                    //Número do Processo
                    //$model->numProcs = date("Y").'/'.str_pad(strval($proxProcesso = Monitoria::find()->count() + 1), 6, '0', STR_PAD_LEFT);
                //}

                if ($model->save()) 
                {
                    $model->file->saveAs('uploads/historicos/'.$aluno->matricula.'_'.date('Ydm_His').'.'.$model->file->extension);
                    return $this->redirect(['view', 'id' => $model->id]);

                } else {

                    if ($model->errors) {
                        //Yii::$app->getSession()->setFlash('danger', $this->convert_multi_array($model->errors));
                        //foreach ($model->getErrors() as $key => $value) {
                        //    Yii::$app->getSession()->setFlash('danger', $key.' - '.$value);
                        //}
                        //foreach (Yii::$app->session->getAllFlashes() as $key => $message) {
                        //    echo '<div class="alert alert-' . $key . '" role="alert">' . $message . '</div>';
                        //}

                        //['IDAluno', 'IDDisc', 'status', 'IDperiodoinscr', 'semestreConclusao', 'anoConclusao', 'mediaFinal']

                        //foreach ($model->getErrors('IDAluno') as $key => $value) {
                        //    Yii::$app->getSession()->setFlash('danger', $key.' - IDAluno: '.$value);
                        //}
                        //foreach ($model->getErrors('IDDisc') as $key => $value) {
                        //    Yii::$app->getSession()->setFlash('danger', $key.' - IDDisc: '.$value);
                        //}
                        //foreach ($model->getErrors('status') as $key => $value) {
                        //    Yii::$app->getSession()->setFlash('danger', $key.' - status: '.$value);
                        //}
                        //foreach ($model->getErrors('IDperiodoinscr') as $key => $value) {
                        //    Yii::$app->getSession()->setFlash('danger', $key.' - IDperiodoinscr: '.$value);
                        //}
                        //foreach ($model->getErrors('semestreConclusao') as $key => $value) {
                        //    Yii::$app->getSession()->setFlash('danger', $key.' - semestreConclusao: '.$value);
                        //}
                        //foreach ($model->getErrors('anoConclusao') as $key => $value) {
                        //    Yii::$app->getSession()->setFlash('danger', $key.' - anoConclusao: '.$value);
                        //}
                        //foreach ($model->getErrors('mediaFinal') as $key => $value) {
                        //    Yii::$app->getSession()->setFlash('danger', $key.' - mediaFinal: '.$value);
                        //}

                        //foreach (Yii::$app->session->getAllFlashes() as $key => $message) {
                        //    echo '<div class="alert alert-' . $key . '" role="alert">' . $message . '</div>';
                        //}

                        //Usuario - Pega aluno baseando-se no CPF do usuário logado
                        $aluno = Usuario::findOne(['CPF' => Yii::$app->user->identity->cpf]);

                        //Seleciona o último período de inscrição
                        $periodoInscricao = PeriodoInscricaoMonitoria::find()->orderBy(['id' => SORT_DESC])->one();

                        return $this->render('create', [
                        'model' => $model,
                        'etapa' => '2',
                        'periodo' => $periodoInscricao->ano .'/'. $periodoInscricao->periodo,
                        'matricula' => $aluno->matricula,
                        ]);

                    }
                }
            }
        } else {

            //Usuario - Pega aluno baseando-se no CPF do usuário logado
            $aluno = Usuario::findOne(['CPF' => Yii::$app->user->identity->cpf]);
            $model->IDAluno = $aluno->id;

            //Status - Aguardando Avaliação
            $model->status = 0;

            //Seleciona o último período de inscrição
            $periodoInscricao = PeriodoInscricaoMonitoria::find()->orderBy(['id' => SORT_DESC])->one();
            $model->IDperiodoinscr = $periodoInscricao->id;

            $searchModel = new DisciplinaMonitoriaSearch();
            $searchModel->numPeriodo = $periodoInscricao->periodo;
            $searchModel->anoPeriodo = $periodoInscricao->ano;
            $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

            return $this->render('create', [
                'model' => $model,
                'etapa' => '1',
                'periodo' => $periodoInscricao->ano .'/'. $periodoInscricao->periodo,
                'matricula' => $aluno->matricula,
                'searchModel' => $searchModel,
                'dataProvider' => $dataProvider,
            ]);
        }
    }

    /**
     * Updates an existing Monitoria model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        if ( (Yii::$app->request->referrer) != '/monitoria/inscricaoaluno')
        {
            $model = $this->findModel($id);

            if ($model->load(Yii::$app->request->post()) && $model->save() ) {
                return $this->redirect(['view', 'id' => $model->id]);
            } else {

                //Usuario - Pega aluno baseando-se no CPF do usuário logado
                $aluno = Usuario::findOne(['CPF' => Yii::$app->user->identity->cpf]);

                //Seleciona o último período de inscrição
                $periodoInscricao = PeriodoInscricaoMonitoria::find()->orderBy(['id' => SORT_DESC])->one();

                return $this->render('update', [
                    'model' => $model,
                    'etapa' => '1',
                    'periodo' => $periodoInscricao->ano .'/'. $periodoInscricao->periodo,
                    'matricula' => $aluno->matricula,
                ]);
            }
        }
        return $this->redirect(Yii::$app->request->referrer);
    }

    /**
     * Deletes an existing Monitoria model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        if (Yii::$app->user->identity->perfil == 1) {
            $this->findModel($id)->delete();
            //$this->redirect(['index']);
        } 

        return $this->redirect(Yii::$app->request->referrer);
    }

    /**
     * Finds the Monitoria model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Monitoria the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Monitoria::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('A página requisitada não existe.');
        }
    }

    public function actionInscricaoaluno()
    {
        //Seleciona o último período de inscrição
        $periodoInscricao = PeriodoInscricaoMonitoria::find()->orderBy(['id' => SORT_DESC])->one();
        $searchModel = new AlunoMonitoriaSearch();
        $dataProvider = $searchModel->searchAluno(Yii::$app->request->queryParams+['AlunoMonitoriaSearch' => ['=', 'periodo' => $periodoInscricao->ano.'/'.$periodoInscricao->periodo]]);

        return $this->render('inscricaoaluno', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionInscricaosecretaria()
    {
        //Seleciona o último período de inscrição
        $periodoInscricao = PeriodoInscricaoMonitoria::find()->orderBy(['id' => SORT_DESC])->one();
        $searchModel = new AlunoMonitoriaSearch();
        $dataProvider = $searchModel->searchSecretaria(Yii::$app->request->queryParams+['AlunoMonitoriaSearch' => ['=', 'periodo' => $periodoInscricao->ano.'/'.$periodoInscricao->periodo]]);

        return $this->render('inscricaosecretaria', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionDeferir($id)
    {
        Yii::$app->db->createCommand()->update('monitoria', ['status' => 1], 'id='.$id)->execute();

        return$this->actionView($id);
    }

    public function actionIndeferir($id)
    {
        Yii::$app->db->createCommand()->update('monitoria', ['status' => 2], 'id='.$id)->execute();
        
        return$this->actionView($id);
    }

    public function actionFormularioinscricao()
    {
        $cssfile = file_get_contents('../web/css/estilo3.css');
        $mpdf = new mPDF('utf-8', 'A4-L');
        $mpdf->title = 'Formulário de Inscrição';
        $mpdf->WriteHTML($cssfile, 1);

        // Cabeçalho + primeira tabela do documento
        $mpdf->WriteHTML('
            <img src="../web/img/cabecalho.png" alt="Universidade Federal do Amazonas...." width="950" height="85">
        ');

        $mpdf->Output();
        exit;
    }

    public function actionFazerplanosemestral()
    {

        return $this->render('fazerplanosemestral');
    }

    public function actionGerarplanosemestraldisciplina()
    {

        return $this->render('gerarplanosemestraldisciplina');
    }

    public function actionGerarquadrogeral()
    {

        $modelPeriodo = DisciplinaPeriodo::find()->orderBy(['anoPeriodo' => SORT_DESC, 'numPeriodo' => SORT_DESC])->one();
        $periodoletivo = $modelPeriodo->anoPeriodo . '/' . $modelPeriodo->numPeriodo;
        $dadosCabecalho = Periodo::find()->where(['codigo' => $periodoletivo])->one();

        if ( $dadosCabecalho != null ) {

            $cssfile = file_get_contents('../web/css/estilo3.css');
            $mpdf = new mPDF('utf-8', 'A4-L');
            $mpdf->title = '3. Quadro Geral';
            $mpdf->WriteHTML($cssfile, 1);
            //$mpdf->Image('../web/img/cabecalho.png', 20, 5, 900, 80);

            // Cabeçalho + primeira tabela do documento
            $mpdf->WriteHTML('
                <img src="../web/img/cabecalho.png" alt="Universidade Federal do Amazonas...." width="950" height="85">

                <p style = "text-align: center;"> <b style = "font-size: small;">
                QUADRO GERAL DE MONITORES BOLSISTAS E NÃO BOLSISTAS - 03 <br> </b>
                (<b style = "background-color: yellow;">Encaminhar também em formato .DOC -word- para o email monitoriaufam@outlook.com</b>)
                </p>

                <table id="cabecalho_1_QuadroGeral" width="99%" height="100%">
                    <tr>
                      <td bgcolor="#e6e6e6"> <b>SETOR RESPONSÁVEL (Coord.Dept/Outros)</b> </td>
                      <td width="30%"></td>
                      <td bgcolor="#e6e6e6"><b>UNIDADE</b></td>
                      <td width="30%">'.$modelPeriodo->nomeUnidade.'</td>
                    </tr> 
                 </table>
                 <table id = "cabecalho_2_QuadroGeral" width="40%" height="100%">
                    <tr>
                      <td bgcolor="#e6e6e6" width="29%"><b>PERÍODO LETIVO</b></td>
                      <td width="20px">'.$modelPeriodo->anoPeriodo.'/'.$modelPeriodo->numPeriodo.' </td> 
                    </tr> 
                 </table>

            ');

            // Tabela do meio do documento
            $mpdf->WriteHTML('
                <br>
                <table id="quadro_geral" width="99%">
                <tr>
                    <td id="n" value="0" bgcolor="#e6e6e6" width="3%" rowspan=2><b>Nº</b></td>
                    <td id="aluno" value="0" bgcolor="#e6e6e6" width="25%" rowspan=2><b>ALUNO</b><br>(nome completo, sem abreviações)</td>
                    <td id="mat" value="0" bgcolor="#e6e6e6" width="6%" rowspan=2><b>Nº <br>MATR.</b></td>
                    <td id="cpf" value="0" bgcolor="#e6e6e6" width="8%" rowspan=2><b>CPF</b></td>
                    <td id="cat" value="0" bgcolor="#e6e6e6" colspan=2 width="6%" colspan=2><b>CATEG.</b></td>
                    <td id="curso" value="0" bgcolor="#e6e6e6" width="13%" rowspan=2><b>CURSO</b></td>
                    <td id="disc" value="0" bgcolor="#e6e6e6" width="14%" rowspan=2><b>DISCIPLINAS</b><br> (código e título, sem abreviações)</td>
                    <td id="prof" value="0" bgcolor="#e6e6e6" width="25%" rowspan=2><b>PROFESSOR ORIENTADOR</b><br> (nome completo, sem abreviações)</td>
                </tr>
                <tr>
                    <td bgcolor="#e6e6e6" width="3%">B</tr>
                    <td bgcolor="#e6e6e6" width="3%">NB</tr>
                </tr>
                 </table>
                 ');

        $aM = AlunoMonitoria::find()->where(['periodo' => $periodoletivo])->orderBy(['aluno' => SORT_DESC])->all();
        $count = count($aM);
        $n = 1;
        $id = 0;

        foreach ($aM as $monitor)        
        {
            $disc = DisciplinaMonitoria::find()->where(['id' => $monitor->id_disciplina])->one();
            $codCurso = Curso::find()->where(['nome' => $monitor->nomeCurso])->one();

            if ( $monitor->bolsa == 0 ) // Row para alunos não-bolsistas
            {
                $mpdf->WriteHTML('
                    <table id="quadro_geral" width="99%">
                        <tr>
                            <td width="3%">'.$n.'</tr>
                            <td width="25%">'.$monitor->aluno.'</tr>
                            <td width="6%">'.$monitor->matricula.'</tr>
                            <td width="8%">'.$monitor->cpf.'</tr>
                            <td width="3%"></tr>
                            <td width="3%">X</tr>
                            <td width="13%">'.$codCurso->codigo.' - '.$monitor->nomeCurso.'</tr>
                            <td width="14%">'.$disc->codDisciplina.' - '.$monitor->nomeDisciplina.'</tr>
                            <td width="25%">'.$monitor->professor.'</tr>
                        </tr>
                         </table>
                ');
            }
            elseif ( $monitor->bolsa == 1 ){  // Row para alunos bolsistas
                $mpdf->WriteHTML('
                    <table id="quadro_geral" width="99%">
                        <tr>
                            <td width="3%">'.$n.'</tr>
                            <td width="25%">'.$monitor->aluno.'</tr>
                            <td width="6%">'.$monitor->matricula.'</tr>
                            <td width="8%">'.$monitor->cpf.'</tr>
                            <td width="3%">X</tr>
                            <td width="3%"></tr>
                            <td width="13%">'.$codCurso->codigo.' - ' .$monitor->nomeCurso.'</tr>
                            <td width="14%">'.$disc->codDisciplina.' - '.$monitor->nomeDisciplina.'</tr>
                            <td width="25%">'.$monitor->professor.'</tr>
                        </tr>
                         </table>
                ');
            }

            $count--;
            $n++;
            $id = $monitor->id;

            if ( $n == 15 ) {   // Para quebrar o doc em páginas.
                $mpdf->AddPage();
                $mpdf->WriteHTML('
                    <img src="../web/img/cabecalho.png" alt="Universidade Federal do Amazonas...." width="950" height="85">
                    <br>
                    <table id="quadro_geral" width="99%">
                    <tr>
                        <td id="n" value="0" bgcolor="#e6e6e6" width="3%"><b>Nº</b></td>
                        <td id="aluno" value="0" bgcolor="#e6e6e6" width="25%"><b>ALUNO</b><br>(nome completo, sem abreviações)</td>
                        <td id="mat" value="0" bgcolor="#e6e6e6" width="6%"><b>Nº <br>MATR.</b></td>
                        <td id="cpf" value="0" bgcolor="#e6e6e6" width="8%"><b>CPF</b></td>
                        <td id="cat" value="0" bgcolor="#e6e6e6" colspan=2 width="6%"><b>CATEG.</b></td>
                        <td id="curso" value="0" bgcolor="#e6e6e6" width="13%"><b>CURSO</b></td>
                        <td id="disc" value="0" bgcolor="#e6e6e6" width="14%"><b>DISCIPLINAS</b><br> (código e título, sem abreviações)</td>
                        <td id="prof" value="0" bgcolor="#e6e6e6" width="25%"><b>PROFESSOR ORIENTADOR</b><br> (nome completo, sem abreviações)</td>
                    </tr>
                    <tr>
                        <td bgcolor="#e6e6e6" width="3%">B</tr>
                        <td bgcolor="#e6e6e6" width="3%">NB</tr>
                    </tr>
                     </table>
                ');
            }
        }

        // Footer do documento
        $mpdf->WriteHTML('

                 <footer> <p>
                    <b>(*)</b> Relacionar todos os monitores de todas as disciplinas do departamento neste mesmo quadro, observando a quantidade total de vagas aprovadas pela Comissão de Monitoria do Programa.<br>
                    <b>(*) B</b> = Bolsista<br>
                    <b>NB</b> = Não Bolsista<br>
                    OBS.: Encaminhar cópia deste quadro à DPA/PROEG para nomeação em portaria. 
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    Manaus, '.date('d').' / '.date('m').' / '.date('Y').'. 
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    _________________________________________________________
                    <br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    Chefe do Depto (com carimbo).    
                    </p>
                </footer>
                ');


            $mpdf->Output();
            exit;
        }
        else return $this->render('gerarquadrogeral');
    }

    public function actionGerarfrequenciageral()
    {

        return $this->render('gerarfrequenciageral');
    }

    public function actionGerarrelatoriosemestral()
    {

        return $this->render('gerarrelatoriosemestral');
    }

    public function actionGerarrelatorioanual()
    {

        return $this->render('gerarrelatorioanual');
    }

    public function convert_multi_array($array) {
      $out = implode("&",array_map(function($a) {return implode("~",$a);},$array));
      return $out;
    }
}
